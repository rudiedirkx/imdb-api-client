<?php

namespace rdx\imdb;

class WatchlistMeta {

	public $count = 0;

	public function __construct(int $count) {
		$this->count = $count;
	}

}
