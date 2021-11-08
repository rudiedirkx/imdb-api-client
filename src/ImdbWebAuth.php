<?php

namespace rdx\imdb;

use GuzzleHttp\Cookie\CookieJar;

class ImdbWebAuth extends ImdbAuth {

	public $user;
	public $pass;

	public function __construct( $user, $pass ) {
		$this->user = $user;
		$this->pass = $pass;

		$this->cookies = new CookieJar();
	}

	public function needsLogin() : bool {
		return true;
	}

}
