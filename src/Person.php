<?php

namespace rdx\imdb;

class Person implements SearchResult {

	public function __construct(
		public string $id,
		public string $name,
		public ?string $searchInfo = null,
		public ?int $birthYear = null,
		public ?Image $image = null,
		public array $credits = [],
	) {}

	public function getSearchInfo() : string {
		$titles = [];
		foreach ($this->credits as $actor) {
			$titles[] = $actor->title->name;
		}
		return implode(', ', $titles);
	}

	public function getSearchResult() : string {
		$info = $this->searchInfo ?? '...';
		return "[PERSON] $this->name ($info)";
	}

	public function getUrl() : string {
		return "https://www.imdb.com/name/$this->id/";
	}

	static public function fromGraphqlNode(array $name) : Person {
		return new Person(
			$name['id'],
			$name['nameText']['text'],
			birthYear: ((int) ($name['birthDate']['date'] ?? $name['birthDate']['dateComponents']['year'] ?? 0)) ?: null,
			image: Image::fromGraphql($name['primaryImage'] ?? []),
			credits: Actor::fromGraphqlPersonCredits($name['knownFor']['edges'] ?? $name['credits']['edges'] ?? []),
		);
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
