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

	public function checkSession() : bool {
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

	public function search( string $query ) : array {
		$clean = trim(preg_replace('#_+#', '_', preg_replace('#[^0-9a-z]+#', '_', strtolower($query))), '_');

		$url = sprintf('https://v2.sg.media-imdb.com/suggestion/%s/%s.json', $clean[0], $clean);

		$rsp = $this->get($url);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);
// print_r($data);

		$results = array_map(function($item) {
			return SearchResult::fromJsonSearch($item);
		}, $data['d']);
		return array_values(array_filter($results));
	}

	public function logIn() : bool {
		return $this->auth->logIn($this) && $this->checkSession();
	}

	protected function getOauthUrl( Node $doc ) {
		foreach ( $doc->queryAll('#signin-options a.list-group-item') as $a ) {
			if ( strpos($a['href'], 'https://www.imdb.com/ap/signin') === 0 ) {
				return $a['href'];
			}
		}
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
