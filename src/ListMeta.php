<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class ListMeta {

	const TYPE_WATCHLIST = 1;
	const TYPE_TITLES = 2;
	const TYPE_PEOPLE = 3;
	const TYPE_RATED = 4;

	const VERSION_2023 = 1;
	const VERSION_2024 = 2;

	public function __construct(
		public int $type,
		public string $name,
		public int $count,
		public ?string $id = null,
		public ?int $version = null,
	) {}

	/**
	 * @return list<self>
	 */
	static public function fromListsDocument(Node $doc) : array {
		$lists = $doc->queryAll('li[data-testid="user-ll-item"]');
		$lists = array_values(array_filter(array_map(function(Node $el) {

			$title = $el->query('a.ipc-metadata-list-summary-item__t');
			if (!$title) return null;

			$meta = static::metaFromDocumentNode($el);
			if (!$meta) return null;

			return new static(
				$meta[0],
				$title->textContent,
				$meta[1],
				static::idFromHref($title['href']),
				static::VERSION_2024,
			);
		}, $lists)));
		return $lists;
	}

	/**
	 * @return ?array{static::TYPE_*, int}
	 */
	static public function metaFromDocumentNode(Node $listEl) : ?array {
		$metas = $listEl->queryAll('.ipc-metadata-list-summary-item__li');
		foreach ($metas as $meta) {
			if (preg_match('#(\d+) (title|titles|person|people)\b#', $meta->textContent, $match)) {
				$items = (int) $match[1];
				$type = in_array($match[2], ['person', 'people']) ? ListMeta::TYPE_PEOPLE : ListMeta::TYPE_TITLES;
				return [$type, $items];
			}
		}
		return null;
	}

	static public function idFromHref(string $href) : ?string {
		if (preg_match('#/list/(ls\d+)/#', $href, $match)) {
			return $match[1];
		}
		return null;
	}

}
