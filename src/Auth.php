<?php

namespace rdx\imdb;

use GuzzleHttp\Cookie\CookieJar;

interface Auth {

	public function cookies() : CookieJar;

	public function logIn(Client $client) : bool;

}
