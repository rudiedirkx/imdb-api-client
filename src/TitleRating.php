<?php

namespace rdx\imdb;

class TitleRating {

	public function __construct(
		public string $id,
		public ?int $rating,
		public ?int $ratedOn = null,
	) {}

	/**
	 * @param ?AssocArray $node
	 */
	static public function fromGraphqlRating(string $id, ?array $node) : static {
		return new static(
			id: $id,
			rating: $node['value'] ?? null,
			ratedOn: empty($node['date']) ? null : strtotime($node['date']),
		);
	}

}
