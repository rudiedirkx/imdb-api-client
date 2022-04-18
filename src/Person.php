<?php

namespace rdx\imdb;

class Person implements SearchResult {

	public function __construct(
		public string $id,
		public string $name,
		public ?string $searchInfo = null,
	) {}

	public function getSearchResult() : string {
		return "[PERSON] $this->name ($this->searchInfo) [$this->id]";
	}

	static public function fromJsonSearch(array $item) {
		return new static(
			$item['id'],
			$item['l'],
			searchInfo: $item['s'],
		);
	}

	static public function idFromHref(string $href) : ?string {
		if (preg_match('#/name/(nm\d+)#', $href, $match)) {
			return $match[1];
		}
		return null;
	}

}
