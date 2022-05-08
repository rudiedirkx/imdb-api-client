<?php

namespace rdx\imdb;

class Person implements SearchResult {

	public function __construct(
		public string $id,
		public string $name,
		public ?string $searchInfo = null,
		public ?int $birthYear = null,
		public array $credits = [],
	) {}

	public function getSearchResult() : string {
		$info = $this->searchInfo ?? '...';
		return "[PERSON] $this->name ($info)";
	}

	public function getUrl() : string {
		return "https://www.imdb.com/name/$this->id/";
	}

	static public function fromJsonSearch(array $item) {
		return new static(
			$item['id'],
			$item['l'],
			searchInfo: $item['s'] ?? null,
		);
	}

	static public function idFromHref(string $href) : ?string {
		if (preg_match('#/name/(nm\d+)#', $href, $match)) {
			return $match[1];
		}
		return null;
	}

}
