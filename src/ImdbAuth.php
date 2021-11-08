<?php

namespace rdx\imdb;

abstract class ImdbAuth {

	public $cookies;

	abstract public function needsLogin() : bool;

}
