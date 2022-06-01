<?php

namespace rdx\imdb;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RedirectMiddleware;
use rdx\jsdom\Node;

class Client {

	protected $auth;
	protected $guzzle;
	public $_requests = [];
	public $watchlist;

	public function __construct( Auth $auth ) {
		$this->auth = $auth;

		$this->guzzle = new Guzzle([
			'http_errors' => false,
			'cookies' => $auth->cookies(),
			'allow_redirects' => [
				'track_redirects' => true,
			] + RedirectMiddleware::$defaultSettings,
		]);
	}

	public function logIn() : bool {
		return $this->auth->logIn($this) && $this->checkSession();
	}

	public function getGraphqlIntrospection() : array {
		$rsp = $this->graphql(file_get_contents(__DIR__ . '/introspection.graphql'));
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);
		unset($data['extensions']);
		return $data;
	}

	public function getGraphqlPerson( string $id ) : ?Person {
		$rsp = $this->graphql(file_get_contents(__DIR__ . '/name.graphql'), [
			'nameId' => $id,
		]);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);
		unset($data['extensions']);
// dump($data['data']['name'] ?? $data);

		$name = $data['data']['name'];
		return new Person(
			$name['id'],
			$name['nameText']['text'],
			birthYear: ((int) ($name['birthDate']['date'] ?? $name['birthDate']['dateComponents']['year'] ?? 0)) ?: null,
			image: Image::fromGraphql($name['primaryImage'] ?? []),
			credits: Actor::fromGraphqlPersonCredits($name['credits']['edges'] ?? []),
		);
	}

	public function getGraphqlTitle( string $id ) : ?Title {
		$rsp = $this->graphql(file_get_contents(__DIR__ . '/title.graphql'), [
			'titleId' => $id,
		]);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);
		unset($data['extensions']);
// dump($data['data']['title'] ?? $data);

		if (empty($data['data']['title']['titleText']['text']) || empty($data['data']['title']['titleType'])) {
			return null;
		}

		return Title::fromGraphqlNode($data['data']['title']);
	}

	public function inWatchlists( array $ids ) : array {
		$rsp = $this->post('https://www.imdb.com/list/_ajax/watchlist_has',	[
			'consts' => [implode(',', $ids)],
			'tracking_tag' => 'watchlistRibbon',
		]);
		$json = (string) $rsp->getBody();
echo "'$json\n\n";
return [];
		if ($this->watchlist) {
			$this->watchlist->id = $data['list_id'];
		}
	}

	public function inWatchlist( string $id ) : bool {
	}

	public function getLists() : array {
		$rsp = $this->get("https://www.imdb.com/profile/lists/");
		$html = (string) $rsp->getBody();
		$doc = Node::create($html);

		return ListMeta::fromListsDocument($doc);
	}

	public function getTitle( string $id ) : ?Title {
		$rsp = $this->get("https://www.imdb.com/title/$id/");
		$html = (string) $rsp->getBody();
		$doc = Node::create($html);

		return Title::fromTitleDocument($id, $doc);
	}

	public function getTitleActors( string $id ) : array {
		$rsp = $this->get("https://www.imdb.com/title/$id/fullcredits/");
		$html = (string) $rsp->getBody();
		$doc = Node::create($html);

		return Actor::fromCreditsDocument($doc);
	}

	public function getTitleRating( string $id ) : TitleRating {
		$rsp = $this->graphql(<<<'GRAPHQL'
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
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);

		return new TitleRating($id, $data['data']['title']['userRating']['value'] ?? null);
	}

	public function rateTitle( string $id, int $rating ) : bool {
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

	public function addTitleToWatchlist( string $id ) : bool {
		$rsp = $this->put("https://www.imdb.com/watchlist/$id");
		if ( $rsp->getStatusCode() != 200 ) return false;

		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);
		return $data && ($data['status'] ?? 0) == 200;
	}

	public function removeTitleFromWatchlist( string $id ) : bool {
		$rsp = $this->delete("https://www.imdb.com/watchlist/$id");
		if ( $rsp->getStatusCode() != 200 ) return false;

		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);
		return $data && ($data['status'] ?? 0) == 200;
	}

	public function searchTitles( string $query ) : array {
		return array_values(array_filter($this->search($query), fn($result) => $result instanceof Title));
	}

	public function searchPeople( string $query ) : array {
		return array_values(array_filter($this->search($query), fn($result) => $result instanceof Person));
	}

	public function search( string $query ) : array {
		$clean = str_replace(["'"], '', strtolower($query));
		$clean = trim(preg_replace('#_+#', '_', preg_replace('#[^0-9a-z]+#', '_', $clean)), '_');

		$url = sprintf('https://v2.sg.media-imdb.com/suggestion/%s/%s.json', $clean[0], $clean);

		$rsp = $this->get($url);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);

		$results = array_map([$this, 'makeSearchResult'], $data['d'] ?? []);
		return array_values(array_filter($results));
	}

	protected function makeSearchResult(array $item) : ?SearchResult {
		if (preg_match('#^tt\d+#', $item['id'])) {
			return Title::fromJsonSearch($item);
		}
		elseif (preg_match('#^nm\d+#', $item['id'])) {
			return Person::fromJsonSearch($item);
		}
		return null;
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

	public function graphql( string $query, array $vars = [] ) : Response {
		$url = 'https://api.graphql.imdb.com/';
		$rsp = $this->guzzle->post($url, [
			// 'headers' => ['Content-type' => 'application/json'],
			'json' => ['query' => $query, 'variables' => $vars],
		]);
		return $this->rememberRequests($url, $rsp);
	}

	protected function post( string $url, array $input ) : Response {
		return $this->rememberRequests($url, $this->guzzle->post($url, [
			'form_params' => $input,
		]));
	}

	protected function put( string $url ) : Response {
		return $this->rememberRequests($url, $this->guzzle->put($url));
	}

	protected function delete( string $url ) : Response {
		return $this->rememberRequests($url, $this->guzzle->delete($url));
	}

	protected function get( string $url ) : Response {
		return $this->rememberRequests($url, $this->guzzle->get($url));
	}

	protected function rememberRequests( string $url, Response $rsp ) : Response {
		if (count($redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER))) {
			$this->_requests[] = [$url, ...$redirects];
		}
		else {
			$this->_requests[] = [$url];
		}

		return $rsp;
	}

}
