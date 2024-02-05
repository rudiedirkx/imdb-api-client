<?php

namespace rdx\imdb;

class TitleRating {

	public function __construct(
		public string $id,
		public ?int $rating,
		public ?int $ratedOn = null,
	) {}

}
