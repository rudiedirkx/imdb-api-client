<?php

namespace rdx\imdb;

class Pager {

	public function __construct(
		public ?int $limit = null,
		public ?string $cursor = null,
	) {}

}
