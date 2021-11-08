<?php

namespace rdx\imdb;

use GuzzleHttp\Cookie\CookieJar;

class ImdbWebAuth {

	public $user;
	public $pass;
	public $cookies;

	public function __construct( $user, $pass ) {
		$this->user = $user;
		$this->pass = $pass;

		$this->cookies = new CookieJar();
	}

}
