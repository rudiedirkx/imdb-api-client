<?php

namespace rdx\imdb;

use GuzzleHttp\Cookie\CookieJar;

class ImdbSessionAuth extends ImdbAuth {

	public function __construct( $atMain, $ubidMain ) {
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

	public function needsLogin() : bool {
		return false;
	}

}
