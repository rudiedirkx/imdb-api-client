<?php

namespace rdx\imdb;

use Exception;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\RedirectMiddleware;
use Psr\Http\Message\ResponseInterface;
use rdx\jsdom\Node;
use RuntimeException;

class Client {

	/** @var AssocArray */
	static public array $userRatingsPersistedQuery = [
		'version' => 1,
		'sha256Hash' => '9672397d6bf156302f8f61e7ede2750222bd2689e65e21cfedc5abd5ca0f4aea',
	];

	protected Auth $auth;
	protected Guzzle $guzzle;
	/** @var list<list<string>> */
	public array $_requests = [];
	public ?ListMeta $watchlist = null;
	public ?ListMeta $ratedlist = null;
	protected Account $account;

	public function __construct(Auth $auth) {
		$this->auth = $auth;

		$this->guzzle = new Guzzle([
			'http_errors' => false,
			'cookies' => $auth->cookies(),
			'headers' => [
				'User-agent' => 'imdb/1.1',
				// 'User-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
			],
			'allow_redirects' => [
				'track_redirects' => true,
			] + RedirectMiddleware::$defaultSettings,
		]);
	}

	public function logIn() : bool {
		return $this->auth->logIn($this) && $this->checkSession();
	}

	/**
	 * @return AssocArray
	 */
	public function getGraphqlIntrospection() : array {
		return $this->graphqlData((string) file_get_contents(__DIR__ . '/introspection.graphql'));
	}

	public function getGraphqlPerson(string $id) : ?Person {
		$data = $this->graphqlData((string) file_get_contents(__DIR__ . '/name.graphql'), [
			'nameId' => $id,
		]);

		if (empty($data['data']['name']['nameText']['text'])) {
			return null;
		}

		return Person::fromGraphqlNode($data['data']['name']);
	}

	public function getGraphqlTitle(string $id) : ?Title {
		$data = $this->graphqlData((string) file_get_contents(__DIR__ . '/title.graphql'), [
			'titleId' => $id,
		]);

		if (empty($data['data']['title']['titleText']['text']) || empty($data['data']['title']['titleType'])) {
			return null;
		}

		return Title::fromGraphqlNode($data['data']['title']);
	}

	/**
	 * @param list<string> $ids
	 * @return list<string>  The tt ids that are in the watchlist
	 */
	public function titlesInWatchlist(array $ids) : array {
		$rsp = $this->post('https://www.imdb.com/list/_ajax/watchlist_has',	[
			'consts[]' => implode(',', $ids) . ',',
			'tracking_tag' => 'watchlistRibbon',
		]);
		$json = (string) $rsp->getBody();

		// tt28015403 in watchlist:
		// {"extra":{"name":"49e6c","value":"4c41"},"has":{"tt28015403":[-1345345678]},"list_id":"ls000529936","status":200}
		// not in watchlist:
		// {"extra":{"name":"49e6c","value":"25c5"},"has":{},"list_id":"ls000529936","status":200}

		$data = json_decode($json, true);
		if (!$data || ($data['status'] ?? 0) !== 200 || !is_array($data['has'] ?? null)) {
			throw new RuntimeException(sprintf('Unexpected watchlist_has response: %s', substr($json, 0, 300)));
		}

		if ($this->watchlist && is_string($data['list_id'] ?? null)) {
			$this->watchlist->id = $data['list_id'];
		}

		return array_keys($data['has']);
	}

	public function titleInWatchlist(string $id) : bool {
		$hasIds = $this->titlesInWatchlist([$id]);
		return in_array($id, $hasIds);
	}

	/**
	 * @return list<ListMeta>
	 */
	public function getLists() : array {
		$rsp = $this->get("https://www.imdb.com/profile/lists/");
		$html = (string) $rsp->getBody();
		$doc = Node::create($html);

		return ListMeta::fromListsDocument($doc);
	}

	public function getTitle(string $id) : ?Title {
		$rsp = $this->get("https://www.imdb.com/title/$id/");
		$html = (string) $rsp->getBody();
		$doc = Node::create($html);

		return Title::fromTitleDocument($id, $doc);
	}

	/**
	 * @return list<Actor>
	 */
	public function getTitleActors(string $id) : array {
		$rsp = $this->get("https://www.imdb.com/title/$id/fullcredits/");
		$html = (string) $rsp->getBody();

		if (preg_match('#<table\s+[^>]+cast_list.+?>[\s\S]+?</table>#', $html, $match)) {
			$html = <<<DOC
				<!doctype html>
				<html lang="en">
				<head><meta charset="utf-8"></head>
				<body>$match[0]</body>
				</html>
			DOC;
		}

		$doc = Node::create($html);

		return Actor::fromCreditsDocument($doc);
	}

	public function getTitleRatingsMeta() : ?ListMeta {
		$this->getTitleRatings();
		return $this->ratedlist;
	}

	/**
	 * @return list<Title>
	 */
	public function getTitleRatings() : array {
		if (file_exists($debugFilepath = __DIR__ . '/imdb-ratings.html')) {
			$html = file_get_contents($debugFilepath);

			$redirects = [];
		}
		else {
			$userId = $this->getUserId();
			$rsp = $this->get("https://www.imdb.com/user/$userId/ratings/");
			$html = (string) $rsp->getBody();
			// file_put_contents($debugFilepath, $html);

			$redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER);
		}
		$dom = Node::create($html);

		$userId = null;
		if (count($redirects)) {
			$url = end($redirects);
			if (preg_match('#/user/(ur\d+)/ratings\b#', $url, $match)) {
				$userId = $match[1];
				$this->setAccount($userId);
			}
		}

		// Old 2023
		$el = $dom->query('.lister-list-length');
		if ($el) {
			if (preg_match('#[\d,]+ \(of ([\d,]+)\) titles#', $el->textContent, $match)) {
				$count = (int) str_replace(',', '', $match[1]);
				$this->ratedlist = new ListMeta(
					ListMeta::TYPE_RATED,
					'Ratings',
					$count,
					id: $userId,
					version: ListMeta::VERSION_2023,
				);
			}

			$titles = [];
			foreach ($dom->queryAll('.lister-item') as $item) {
				if ($title = Title::fromListItem2023($item)) {
					$titles[] = $title;
				}
			}

			return $titles;
		}

		$titles = [];

		// New 2024
		// ~250 from JSON
		$el = $dom->query('script#__NEXT_DATA__');
		if ($el) {
			$json = $el->textContent;
			$data = json_decode($json, true);
			if (isset($data['props']['pageProps']['mainColumnData']['advancedTitleSearch']['edges'][0]['node'])) {
				$advancedTitleSearch = $data['props']['pageProps']['mainColumnData']['advancedTitleSearch'];
				$count = $advancedTitleSearch['total'];

				$this->ratedlist = new ListMeta(
					ListMeta::TYPE_RATED,
					'Ratings',
					$count,
					id: $userId,
					version: ListMeta::VERSION_2024,
				);

				foreach (array_slice($advancedTitleSearch['edges'], 0, 150) as $edge) {
					$titles[] = Title::fromGraphqlNode($edge['node']['title']);
				}
			}
		}

		if (!count($titles)) {
			// ~25 from HTML
			$el = $dom->query('[data-testid="list-page-mc-total-items"]');
			if ($el) {
				if (preg_match('#^([\d,]+) titles#', $el->textContent, $match)) {
					$count = (int) str_replace(',', '', $match[1]);
					$this->ratedlist = new ListMeta(
						ListMeta::TYPE_RATED,
						'Ratings',
						$count,
						id: $userId,
						version: ListMeta::VERSION_2024,
					);
				}

				foreach ($dom->queryAll('.ipc-metadata-list-summary-item') as $item) {
					if ($title = Title::fromListItem2024($item)) {
						$titles[] = $title;
					}
				}
			}
		}

		if (!count($titles)) return [];

		// Plus GraphQL data
		$body = [
			'operationName' => 'UserRatingsAndWatchOptions',
			'variables' => [
				'locale' => 'en-US',
				'idArray' => array_column($titles, 'id'),
				'includeUserRating' => true,
				'location' => [
					'latLong' => [
						'lat' => '52.35',
						'long' => '4.89',
					],
				],
				'fetchOtherUserRating' => false,
			],
			'extensions' => [
				'persistedQuery' => static::$userRatingsPersistedQuery,
			],
		];
		try {
			$rsp = $this->graphqlRaw($body);
			$json = (string) $rsp->getBody();
			$data = $this->unpackGraphqlJson($json);

			$ratings = array_column($data['data']['titles'], 'userRating', 'id');
			foreach ($titles as $title) {
				if (isset($ratings[$title->id])) {
					$rating = $ratings[$title->id];
					$title->userRating = new TitleRating($title->id, $rating['value'], ratedOn: strtotime($rating['date']));
				}
			}
		}
		catch (Exception $ex) {
			// Ignore
		}

		return $titles;
	}

	public function getTitleRating(string $id) : TitleRating {
		$data = $this->graphqlData(<<<'GRAPHQL'
		query GetTitle($titleId: ID!) {
			title(id: $titleId) {
				id
				userRating {
					value
				}
			}
		}
		GRAPHQL, [
			'titleId' => $id,
		]);

		return new TitleRating($id, $data['data']['title']['userRating']['value'] ?? null);
	}

	public function rateTitle(string $id, int $rating) : bool {
		$rsp = $this->graphql(<<<'GRAPHQL'
		mutation UpdateTitleRating($rating: Int!, $titleId: ID!) {
			rateTitle(input: {rating: $rating, titleId: $titleId}) {
				rating {
					value
				}
			}
		}
		GRAPHQL, [
			'rating' => $rating,
			'titleId' => $id,
		]);

		return $rsp->getStatusCode() == 200;
	}

	public function addTitleToWatchlist(string $id) : bool {
		$rsp = $this->put("https://www.imdb.com/watchlist/$id");
		if ($rsp->getStatusCode() != 200) {
			throw new RuntimeException(sprintf("Watchlist http code = %d", $rsp->getStatusCode()));
		}

		// {"list_id":"ls123456789","list_item_id":"234567890","status":200} = new
		// {"list_id":"ls123456789","list_item_id":"0","status":200} = exists
		$json = trim((string) $rsp->getBody());
// echo "\n\n$json\n\n";
		$data = json_decode($json, true);
		if (!$data || ($data['status'] ?? 0) !== 200) {
			throw new RuntimeException(sprintf("Watchlist response status = %s", $data['status'] ?? '?'));
		}

		return !empty($data['list_item_id']);
	}

	public function removeTitleFromWatchlist(string $id) : bool {
		$rsp = $this->delete("https://www.imdb.com/watchlist/$id");
		if ($rsp->getStatusCode() != 200) {
			throw new RuntimeException(sprintf("Watchlist http code = %d", $rsp->getStatusCode()));
		}

		// {"list_id":"ls123456789","status":200}
		$json = trim((string) $rsp->getBody());
// echo "\n\n$json\n\n";
		$data = json_decode($json, true);
		if (!$data || ($data['status'] ?? 0) !== 200) {
			throw new RuntimeException(sprintf("Watchlist response status = %s", $data['status'] ?? '?'));
		}

		return true;
	}

	/**
	 * @return list<SearchResult>
	 */
	public function searchTitles(string $query) : array {
		return array_values(array_filter($this->search($query), fn($result) => $result instanceof Title));
	}

	/**
	 * @return list<SearchResult>
	 */
	public function searchPeople(string $query) : array {
		return array_values(array_filter($this->search($query), fn($result) => $result instanceof Person));
	}

	/**
	 * @param AssocArray $options
	 * @return list<SearchResult>
	 */
	public function searchGraphql(string $query, array $options = []) : array {
		$data = $this->graphqlData((string) file_get_contents(__DIR__ . '/search.graphql'), [
			'query' => $query,
			'first' => $options['limit'] ?? 20,
			'types' => $options['types'] ?? ['TITLE', 'NAME'],
		]);

		$results = array_map($this->makeGraphqlSearchResult(...), $data['data']['mainSearch']['edges'] ?? []);
		return array_values(array_filter($results));
	}

	/**
	 * @param AssocArray $item
	 */
	protected function makeGraphqlSearchResult(array $item) : ?SearchResult {
		$item = $item['node']['entity'];
		if (preg_match('#^tt\d+#', $item['id'] ?? '')) {
			return Title::fromGraphqlNode($item);
		}
		elseif (preg_match('#^nm\d+#', $item['id'] ?? '')) {
			return Person::fromGraphqlNode($item);
		}
		return null;
	}

	/**
	 * @return list<SearchResult>
	 */
	public function search(string $query) : array {
		$clean = str_replace(["'"], '', strtolower($query));
		$clean = trim(preg_replace('#_+#', '_', preg_replace('#[^0-9a-z]+#', '_', $clean)), '_');

		$url = sprintf('https://v3.sg.media-imdb.com/suggestion/x/%s.json', $clean);

		$rsp = $this->get($url);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);

		$results = array_map($this->makeJsonSearchResult(...), $data['d'] ?? []);
		return array_values(array_filter($results));
	}

	/**
	 * @param AssocArray $item
	 */
	protected function makeJsonSearchResult(array $item) : ?SearchResult {
		if (preg_match('#^tt\d+#', $item['id'])) {
			return Title::fromJsonSearch($item);
		}
		elseif (preg_match('#^nm\d+#', $item['id'])) {
			return Person::fromJsonSearch($item);
		}
		return null;
	}

	protected function setAccount(?string $userId, ?string $name = null) : void {
		$this->account ??= new Account($userId);
		if ($userId) $this->account->userId = $userId;
		if ($name) $this->account->name = $name;
	}

	protected function getAccount() : Account {
		if (isset($this->account)) {
			return $this->account;
		}

		$rsp = $this->get('https://www.imdb.com/');
		if ($rsp->getStatusCode() != 200) {
			throw new RuntimeException("Homepage failed to load!? HTTP code " . $rsp->getStatusCode());
		}

		$html = (string) $rsp->getBody();
		$dom = Node::create($html);

		$el = $dom->query('script#__NEXT_DATA__');
		if (!$el) {
			throw new RuntimeException("Homepage doesn't have __NEXT_DATA__ element");
		}

		$json = $el->textContent;
		$data = json_decode($json, true);
		if (!$data || !is_array($data)) {
			throw new RuntimeException("Homepage __NEXT_DATA__ is unreadable");
		}

		$account = $data['props']['pageProps']['requestContext']['sidecar']['account'] ?? null;
		$userId = strval($account['userId'] ?? '') ?: null;
		$name = strval($account['userName'] ?? '') ?: null;

		$this->setAccount($userId, $name);
		return $this->account;
	}

	protected function getUserId() : ?string {
		return $this->getAccount()->userId;
	}

	protected function checkSession() : bool {
		$rsp = $this->get('https://www.imdb.com/_ajax/list/watchlist/count');
		if ($rsp->getStatusCode() != 200) {
			return false;
		}

		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);
		if (isset($data['count'])) {
			$this->watchlist = new ListMeta(ListMeta::TYPE_WATCHLIST, 'Watchlist', $data['count']);
		}

		return true;
	}

	/**
	 * @return AssocArray
	 */
	protected function unpackGraphqlJson(string $json) : array {
		$data = json_decode($json, true);
		if (!$data) {
			throw new RuntimeException(sprintf("Invalid JSON response: %s", substr($json, 0, 100)));
		}

		if (isset($data['errors'][0])) {
			throw new GraphqlException($data['errors'][0]['message']);
		}

		return $data;
	}

	/**
	 * @param AssocArray $vars
	 * @return AssocArray
	 */
	public function graphqlData(string $query, array $vars = []) : array {
		$rsp = $this->graphql($query, $vars);
		$json = (string) $rsp->getBody();
		return $this->unpackGraphqlJson($json);
	}

	/**
	 * @param AssocArray $vars
	 */
	public function graphql(string $query, array $vars = []) : ResponseInterface {
		return $this->graphqlRaw(['query' => $query, 'variables' => (object) $vars]);
	}

	/**
	 * @param AssocArray $body
	 */
	protected function graphqlRaw(array $body = []) : ResponseInterface {
		$url = 'https://api.graphql.imdb.com/';
		$rsp = $this->guzzle->post($url, [
			// 'headers' => ['Content-type' => 'application/json'],
			'json' => $body,
		]);
		return $this->rememberRequests('POST', $url, $rsp);
	}

	/**
	 * @param AssocArray $input
	 */
	protected function post(string $url, array $input) : ResponseInterface {
		return $this->rememberRequests('POST', $url, $this->guzzle->post($url, [
			'form_params' => $input,
		]));
	}

	protected function put(string $url) : ResponseInterface {
		return $this->rememberRequests('PUT', $url, $this->guzzle->put($url));
	}

	protected function delete(string $url) : ResponseInterface {
		return $this->rememberRequests('DELETE', $url, $this->guzzle->delete($url));
	}

	protected function get(string $url) : ResponseInterface {
		return $this->rememberRequests('GET', $url, $this->guzzle->get($url));
	}

	protected function rememberRequests(string $method, string $url, ResponseInterface $rsp) : ResponseInterface {
		if (count($redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER))) {
			$this->_requests[] = [$method, $url, ...array_values($redirects)];
		}
		else {
			$this->_requests[] = [$method, $url];
		}

		return $rsp;
	}

}
