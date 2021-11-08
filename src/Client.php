<?php

namespace rdx\imdb;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\RedirectMiddleware;
use rdx\jsdom\Node;

class Client {

	protected $auth;
	protected $guzzle;

	public function __construct( ImdbWebAuth $auth ) {
		$this->auth = $auth;

		$this->guzzle = new Guzzle([
			'http_errors' => false,
			'cookies' => $auth->cookies,
			'allow_redirects' => [
				'track_redirects' => true,
			] + RedirectMiddleware::$defaultSettings,
		]);
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

}
