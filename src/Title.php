<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class Title implements SearchResult {

	public function __construct(
		public string $id,
		public string $name,
		public ?int $year = null,
		public ?string $plot = null,
		public ?string $searchInfo = null,
		public array $actors = [],
		public ?float $rating = null,
		public ?TitleRating $userRating = null,
	) {}

	public function getSearchResult() : string {
		$year = $this->year ?? '?';
		$info = $this->searchInfo ?? '...';
		return "[TITLE] $this->name ($year) ($info)";
	}

	public function getUrl() : string {
		return "https://www.imdb.com/title/$this->id/";
	}

	static public function fromJsonSearch(array $item) {
		return new static(
			$item['id'],
			$item['l'],
			year: $item['y'] ?? null,
			searchInfo: $item['s'] ?? null,
		);
	}

	static public function fromTitleDocument(string $id, Node $doc) {
		$h1 = $doc->query('h1');
		$desc = $doc->query('[data-testid="plot-xl"]');
		$year = static::getYear($id, $doc);
		$rating = $doc->query('[data-testid="hero-rating-bar__aggregate-rating__score"]');
		$actors = Actor::fromTitleDocument($doc);

		return new static(
			$id,
			$h1->textContent,
			year: $year,
			plot: $desc->textContent,
			rating: $rating ? (float) $rating->textContent : null,
			actors: $actors,
		);
	}

	static protected function getYear(string $id, Node $doc) : ?int {
		foreach ($doc->queryAll('[href^="/title/' . $id . '/releaseinfo"]') as $el) {
			$text = trim($el->textContent);
			if (preg_match('#^(\d\d\d\d)\b#', $text, $match)) {
				return $match[1];
			}
		}
		return null;
	}

}
