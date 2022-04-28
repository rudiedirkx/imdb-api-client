<?php

namespace rdx\imdb;

use GuzzleHttp\Cookie\CookieJar;

class AuthSession implements Auth {

	protected $cookies;

	public function __construct(string $atMain, string $ubidMain) {
		$this->cookies = new CookieJar(false, [
			[
				'Domain' => '.imdb.com',
				'Name' => 'at-main',
				'Value' => $atMain,
			],
			[
				'Domain' => '.imdb.com',
				'Name' => 'ubid-main',
				'Value' => $ubidMain,
			],
		]);
	}

	public function cookies() : CookieJar {
		return $this->cookies;
	}

	public function logIn(Client $client) : bool {
		return true;
	}

}
