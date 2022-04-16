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

	public function __construct( ImdbAuth $auth ) {
		$this->auth = $auth;

		$this->guzzle = new Guzzle([
			'http_errors' => false,
			'cookies' => $auth->cookies,
			'allow_redirects' => [
				'track_redirects' => true,
			] + RedirectMiddleware::$defaultSettings,
		]);
	}

	public function authNeedsLogin() : bool {
		return $this->auth->needsLogin();
	}

	protected function extractWatchlistMeta(string $html) : void {
		if (preg_match('#"total":(\d+)#', $html, $match)) {
			$this->watchlist = new WatchlistMeta($match[1]);
		}
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

	public function logIn() {
		$rsp = $this->guzzle->get('https://www.imdb.com/registration/signin');
print_r($rsp);
		$html = (string) $rsp->getBody();
// echo "$html\n";
		$doc = Node::create($html);

		$startUrl = $this->getOauthUrl($doc);
var_dump($startUrl);

		$rsp = $this->guzzle->get($startUrl);
print_r($rsp);
		$html = (string) $rsp->getBody();
echo "$html\n\n\n\n================\n\n\n\n";
		$doc = Node::create($html);

		$form = $doc->query('form[method="post"]');
		$values = $form->getFormValues();
		$data = [
			'email' => $this->auth->user,
			'password' => $this->auth->pass,
			// 'metadata1' => '',
		] + $values;
print_r($data);

		usleep(rand(500, 1500));

		$rsp = $this->guzzle->post('https://www.imdb.com/ap/signin', [
			'form_data' => $data,
		]);
print_r($rsp);
		$html = (string) $rsp->getBody();
echo "$html\n\n\n\n================\n\n\n\n";
// echo strip_tags($html);
	}

	protected function getOauthUrl( Node $doc ) {
		foreach ( $doc->queryAll('#signin-options a.list-group-item') as $a ) {
			if ( strpos($a['href'], 'https://www.imdb.com/ap/signin') === 0 ) {
				return $a['href'];
			}
		}
	}

	protected function get( string $url ) : Response {
		$rsp = $this->guzzle->get($url);
		if (count($redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER))) {
			$this->_requests[] = [$url, ...$redirects];
		}
		else {
			$this->_requests[] = [$url];
		}

		return $rsp;
	}

}
