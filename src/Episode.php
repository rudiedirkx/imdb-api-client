<?php

namespace rdx\imdb;

class Episode {

	public function __construct(
		public ?Title $series,
		public ?int $season,
		public ?int $episode,
	) {}

	/**
	 * @param AssocArray $node
	 */
	static public function fromGraphqlNode(array $node) : ?static {
		$title = empty($node['series']) ? null : Title::fromGraphqlNode($node['series']);
		return new static($title,
			season: $node['episodeNumber']['seasonNumber'],
			episode: $node['episodeNumber']['episodeNumber'],
		);
	}

}
