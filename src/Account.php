<?php

namespace rdx\imdb;

class Account {

	public function __construct(
		public ?string $userId,
		public ?string $name = null,
	) {}

}
