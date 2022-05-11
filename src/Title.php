<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class Title implements SearchResult {

	const TYPE_MOVIE = 1;
	const TYPE_SERIES = 2;
	const TYPE_EPISODE = 3;
	const TYPE_GAME = 4;
	const TYPES = [
		self::TYPE_MOVIE => 'movie',
		self::TYPE_SERIES => 'series',
		self::TYPE_EPISODE => 'episode',
		self::TYPE_GAME => 'game',
	];

	public function __construct(
		public string $id,
		public string $name,
		public ?int $type = null,
		public ?int $year = null,
		public ?int $endYear = null,
		public ?string $plot = null,
		public ?int $duration = null,
		public ?string $searchInfo = null,
		public array $actors = [],
		public ?float $rating = null,
		public ?int $ratings = null,
		public ?TitleRating $userRating = null,
		public ?Image $image = null,
	) {}

	public function getSearchResult() : string {
		$year = $this->year ?? '?';
		$info = $this->searchInfo ?? '...';
		$type = $this->getTypeLabel() ?? 'TITLE';
		return "[$type] $this->name ($year) ($info)";
	}

	public function getDurationLabel() : ?string {
		if ($this->duration === null) return null;

		$m = round($this->duration / 60);
		if ($m < 60) {
			return $m . 'm';
		}

		return floor($m / 60) . 'h ' . ($m % 60) . 'm';
	}

	public function getTypeLabel() : ?string {
		return $this->type && isset(self::TYPES[$this->type]) ? strtoupper(self::TYPES[$this->type]) : null;
	}

	public function getYearLabel() : ?string {
		if ($this->year === null) return null;
		if ($this->type === self::TYPE_SERIES) {
			return $this->year . ' - ' . ($this->endYear ?? '?');
		}
		return $this->year;
	}

	public function getUrl() : string {
		return "https://www.imdb.com/title/$this->id/";
	}

	static public function typeFromTitleType(string $typeId) : ?int {
		switch ($typeId) {
			// GraphQL `titleType.id`
			case 'movie':
			case 'short':
			case 'tvShort':
			case 'tvSpecial':
			case 'tvMovie':
			// JSON search `q`
			case 'feature':
			case 'TV movie':
			case 'TV short':
			case 'TV special':
				return self::TYPE_MOVIE;

			// GraphQL `titleType.id`
			case 'tvSeries':
			case 'tvMiniSeries':
			case 'podcastSeries':
			// JSON search `q`
			case 'TV series':
			case 'TV mini-series':
				return self::TYPE_SERIES;

			// GraphQL `titleType.id`
			case 'tvEpisode':
			case 'podcastEpisode':
			// JSON search `q`
			case 'TV episode':
				return self::TYPE_EPISODE;

			// GraphQL `titleType.id`
			case 'videoGame':
			case 'video game':
				return self::TYPE_GAME;
		}

		return null;
	}

	static public function fromJsonSearch(array $item) : Title {
		return new static(
			$item['id'],
			$item['l'],
			type: self::typeFromTitleType($item['q'] ?? ''),
			year: $item['y'] ?? null,
			searchInfo: $item['s'] ?? null,
		);
	}

	static public function fromGraphqlNode(array $title) : Title {
// dump($title);
		return new static(
			$title['id'],
			$title['titleText']['text'],
			type: self::typeFromTitleType($title['titleType']['id'] ?? ''),
			year: $title['releaseYear']['year'] ?? null,
			endYear: $title['releaseYear']['endYear'] ?? null,
			plot: $title['plots']['edges'][0]['node']['plotText']['plainText'] ?? null,
			duration: $title['runtime']['seconds'] ?? null,
			rating: $title['ratingsSummary']['aggregateRating'] ?? null,
			ratings: $title['ratingsSummary']['voteCount'] ?? null,
			userRating: array_key_exists('userRating', $title) ? new TitleRating($title['id'], $title['userRating']['value'] ?? null) : null,
			image: Image::fromGraphql($title['primaryImage'] ?? []),
			actors: Actor::fromGraphqlTitleCredits($title['credits']['edges'] ?? []),
		);
	}

	static public function fromTitleDocument(string $id, Node $doc) : ?Title {
		$h1 = $doc->query('h1');
		$desc = $doc->query('[data-testid="plot-xl"]');
		$year = static::getYear($id, $doc);
		$rating = $doc->query('[data-testid="hero-rating-bar__aggregate-rating__score"]');
		$actors = Actor::fromTitleDocument($doc);

		$genres = $doc->query('[data-testid="genres"]');
		if (strpos($h1->textContent, '404') !== false || !$genres) {
			return null;
		}

		return new static(
			$id,
			$h1->textContent,
			year: $year,
			plot: $desc->textContent ?? null,
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
