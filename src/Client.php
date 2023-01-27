<?php

namespace rdx\imdb;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RedirectMiddleware;
use rdx\jsdom\Node;
use RuntimeException;

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

		return Person::fromGraphqlNode($data['data']['name']);
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

	public function titlesInWatchlist( array $ids ) : array {
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

	public function titleInWatchlist( string $id ) : bool {

		//
		// Crazy & inefficient, BUT it works, unlike titlesInWatchlist()
		//

		$added = $this->addTitleToWatchlist($id);
		if ($added) {
			$this->removeTitleFromWatchlist($id);
			return false;
		}

		return true;
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
		if ( $rsp->getStatusCode() != 200 ) {
			throw new RuntimeException(sprintf("Watchlist http code = %d", $rsp->getStatusCode()));
		}

		// {"list_id":"ls123456789","list_item_id":"234567890","status":200} = new
		// {"list_id":"ls123456789","list_item_id":"0","status":200} = exists
		$json = trim((string) $rsp->getBody());
// echo "\n\n$json\n\n";
		$data = json_decode($json, true);
		if ( !$data || ($data['status'] ?? 0) != 200 ) {
			throw new RuntimeException(sprintf("Watchlist response status = %s", $data['status'] ?? '?'));
		}

		return !empty($data['list_item_id']);
	}

	public function removeTitleFromWatchlist( string $id ) : bool {
		$rsp = $this->delete("https://www.imdb.com/watchlist/$id");
		if ( $rsp->getStatusCode() != 200 ) {
			throw new RuntimeException(sprintf("Watchlist http code = %d", $rsp->getStatusCode()));
		}

		// {"list_id":"ls123456789","status":200}
		$json = trim((string) $rsp->getBody());
// echo "\n\n$json\n\n";
		$data = json_decode($json, true);
		if ( !$data || ($data['status'] ?? 0) != 200 ) {
			throw new RuntimeException(sprintf("Watchlist response status = %s", $data['status'] ?? '?'));
		}

		return true;
	}

	public function searchTitles( string $query ) : array {
		return array_values(array_filter($this->search($query), fn($result) => $result instanceof Title));
	}

	public function searchPeople( string $query ) : array {
		return array_values(array_filter($this->search($query), fn($result) => $result instanceof Person));
	}

	public function searchGraphql( string $query ) : array {
		$rsp = $this->graphql(file_get_contents(__DIR__ . '/search.graphql'), [
			'query' => $query,
		]);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);

		$results = array_map([$this, 'makeGraphqlSearchResult'], $data['data']['mainSearch']['edges'] ?? []);
		return array_values(array_filter($results));
	}

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

	public function search( string $query ) : array {
		$clean = str_replace(["'"], '', strtolower($query));
		$clean = trim(preg_replace('#_+#', '_', preg_replace('#[^0-9a-z]+#', '_', $clean)), '_');

		$url = sprintf('https://v3.sg.media-imdb.com/suggestion/x/%s.json', $clean);

		$rsp = $this->get($url);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);

		$results = array_map([$this, 'makeJsonSearchResult'], $data['d'] ?? []);
		return array_values(array_filter($results));
	}

	protected function makeJsonSearchResult(array $item) : ?SearchResult {
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
		return $this->rememberRequests('POST', $url, $rsp);
	}

	protected function post( string $url, array $input ) : Response {
		return $this->rememberRequests('POST', $url, $this->guzzle->post($url, [
			'form_params' => $input,
		]));
	}

	protected function put( string $url ) : Response {
		return $this->rememberRequests('PUT', $url, $this->guzzle->put($url));
	}

	protected function delete( string $url ) : Response {
		return $this->rememberRequests('DELETE', $url, $this->guzzle->delete($url));
	}

	protected function get( string $url ) : Response {
		return $this->rememberRequests('GET', $url, $this->guzzle->get($url));
	}

	protected function rememberRequests( string $method, string $url, Response $rsp ) : Response {
		if (count($redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER))) {
			$this->_requests[] = [$method, $url, ...$redirects];
		}
		else {
			$this->_requests[] = [$url];
		}

		return $rsp;
	}

}
