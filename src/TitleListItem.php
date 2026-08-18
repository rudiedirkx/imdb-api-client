<?php

namespace rdx\imdb;

class TitleListItem {

	public function __construct(
		public Title $title,
		public ?int $created,
		public ?int $position,
		public ?string $notes = null,
	) {}

	/**
	 * @param AssocArray $node
	 */
	static public function fromGraphqlNode(array $node) : self {
		return new self(
			Title::fromGraphqlNode($node['listItem']),
			strtotime($node['createdDate']),
			$node['absolutePosition'],
			$node['description']['originalText']['plainText'] ?? null,
		);
	}

}
