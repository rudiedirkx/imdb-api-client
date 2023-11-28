<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class ListMeta {

	const TYPE_WATCHLIST = 1;
	const TYPE_TITLES = 2;
	const TYPE_PEOPLE = 3;
	const TYPE_RATED = 4;

	public function __construct(
		public int $type,
		public string $name,
		public int $count,
		public ?string $id = null,
	) {}

	static public function fromListsDocument(Node $doc) : array {
		$lists = $doc->queryAll('li.user-list');
		$lists = array_map(function($el) {
			$title = $el->query('a');
			$meta = $el->query('.list-meta');
			preg_match('#(\d+) (title|titles|person|people)#', $meta->textContent, $match);
			return new static(
				in_array($match[2], ['person', 'people']) ? ListMeta::TYPE_PEOPLE : ListMeta::TYPE_TITLES,
				$title->textContent,
				$match[1],
				self::idFromHref($title['href']));
		}, $lists);
		return $lists;
	}

	static public function idFromHref(string $href) : ?string {
		if (preg_match('#/list/(ls\d+)/#', $href, $match)) {
			return $match[1];
		}
		return null;
	}

}
