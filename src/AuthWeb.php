<?php

namespace rdx\imdb;

use GuzzleHttp\Cookie\CookieJar;

class AuthWeb implements Auth {

	protected $user;
	protected $pass;
	protected $cookies;

	public function __construct( string $user, string $pass ) {
		$this->user = $user;
		$this->pass = $pass;

		$this->cookies = new CookieJar();
	}

	public function cookies() : CookieJar {
		return $this->cookies;
	}

	public function logIn(Client $client) : bool {
return false;

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
