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

	public function getGraphqlTitle( string $id ) : Title {
		$rsp = $this->graphql(file_get_contents(__DIR__ . '/title.graphql'), [
			'titleId' => $id,
		]);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);
		unset($data['extensions']);
// print_r($data);

		$title = $data['data']['title'];
		return new Title(
			$title['id'],
			$title['titleText']['text'],
			year: $title['releaseYear']['year'],
			plot: $title['plots']['edges'][0]['node']['plotText']['plainText'],
			rating: $title['ratingsSummary']['aggregateRating'],
			userRating: new TitleRating($title['id'], $title['userRating']['value'] ?? null),
			actors: Actor::fromGraphqlCredits($title['credits']['edges']),
		);
	}

	public function getTitle( string $id ) : Title {
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

	public function searchTitles( string $query ) : array {
		return array_values(array_filter($this->search($query), fn($result) => $result instanceof Title));
	}

	public function searchPeople( string $query ) : array {
		return array_values(array_filter($this->search($query), fn($result) => $result instanceof Person));
	}

	public function search( string $query ) : array {
		$clean = trim(preg_replace('#_+#', '_', preg_replace('#[^0-9a-z]+#', '_', strtolower($query))), '_');

		$url = sprintf('https://v2.sg.media-imdb.com/suggestion/%s/%s.json', $clean[0], $clean);

		$rsp = $this->get($url);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);

		$results = array_map([$this, 'makeSearchResult'], $data['d']);
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
			$this->watchlist = new WatchlistMeta($data['count']);
		}

		return true;
	}

	protected function graphql( string $query, array $vars = [] ) : Response {
		$url = 'https://api.graphql.imdb.com/';
		$rsp = $this->guzzle->post($url, [
			// 'headers' => ['Content-type' => 'application/json'],
			'json' => ['query' => $query, 'variables' => $vars],
		]);
		return $this->rememberRequests($url, $rsp);
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
