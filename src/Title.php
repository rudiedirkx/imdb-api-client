<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class Title implements SearchResult {

	const TYPE_MOVIE = 1;
	const TYPE_SERIES = 2;
	const TYPE_EPISODE = 3;
	const TYPE_GAME = 4;
	const TYPE_MUSIC = 5;
	const TYPE_PODCAST = 6;
	const TYPES = [
		self::TYPE_MOVIE => 'movie',
		self::TYPE_SERIES => 'series',
		self::TYPE_EPISODE => 'episode',
		self::TYPE_GAME => 'game',
		self::TYPE_MUSIC => 'music',
		self::TYPE_PODCAST => 'podcast',
	];

	public function __construct(
		public string $id,
		public string $name,
		public ?string $originalName = null,
		public ?int $type = null,
		// public ?Title $series = null,
		public ?Episode $episode = null,
		/** @var list<Title> */
		public array $episodes = [],
		/** @var list<string> */
		public array $genres = [],
		public ?int $year = null,
		public ?int $endYear = null,
		public ?string $plot = null,
		public ?int $duration = null,
		public ?string $searchInfo = null,
		/** @var list<Actor> */
		public array $actors = [],
		/** @var list<Person> */
		public array $directors = [],
		/** @var list<Person> */
		public array $writers = [],
		public ?float $rating = null,
		public ?int $ratings = null,
		public ?TitleRating $userRating = null,
		public ?Image $image = null,
	) {
		if ($this->originalName === $this->name) {
			$this->originalName = null;
		}
	}

	public function getSearchInfo() : string {
		$actors = [];
		foreach ($this->actors as $actor) {
			$actors[] = $actor->person->name;
		}
		return implode(', ', $actors);
	}

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
		return (string) $this->year;
	}

	public function getUrl() : string {
		return "https://www.imdb.com/title/$this->id/";
	}

	static public function getIdFromHref(string $href) : ?string {
		if (preg_match('#/title/(tt\d+)/#', $href, $match)) {
			return $match[1];
		}
		return null;
	}

	static public function getDurationFromLabel(string $duration) : ?int {
		if (preg_match('#^(\d+)(?:h| hr) (\d+)(?:m| min)$#', $duration, $match)) {
			return intval($match[1]) * 3600 + intval($match[2]) * 60 + 1;
		}
		elseif (preg_match('#^(\d+)(?:h| hr)$#', $duration, $match)) {
			return intval($match[1]) * 3600 + 1;
		}
		elseif (preg_match('#^(\d+)(?:m| min)$#', $duration, $match)) {
			return intval($match[1]) * 60 + 1;
		}

		return null;
	}

	static public function typeFromTitleType(string $typeId) : ?int {
		switch ($typeId) {
			// GraphQL `titleType.id`
			case 'movie':
			case 'short':
			case 'tvShort':
			case 'tvSpecial':
			case 'tvMovie':
			case 'video':
			// JSON search `q`
			case 'feature':
			case 'TV movie':
			case 'TV short':
			case 'TV special':
				return self::TYPE_MOVIE;

			// GraphQL `titleType.id`
			case 'tvSeries':
			case 'tvMiniSeries':
			// JSON search `q`
			case 'TV series':
			case 'TV mini-series':
				return self::TYPE_SERIES;

			// GraphQL `titleType.id`
			case 'podcastSeries':
				return self::TYPE_PODCAST;

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

			// GraphQL `titleType.id`
			case 'musicVideo':
				return self::TYPE_MUSIC;
		}

		return null;
	}

	/**
	 * @param AssocArray $item
	 */
	static public function fromJsonSearch(array $item) : Title {
// dump($item);
		return new static(
			$item['id'],
			$item['l'],
			type: self::typeFromTitleType($item['q'] ?? ''),
			year: $item['y'] ?? null,
			searchInfo: $item['s'] ?? null,
			image: Image::fromJsonSearch($item['i'] ?? []),
		);
	}

	/**
	 * @param AssocArray $node
	 */
	static public function fromGraphqlRatingsNode(array $node) : Title {
		$title = static::fromGraphqlNode($node['title']);
		$title->userRating = TitleRating::fromGraphqlRating($title->id, $node['userRating']);
		return $title;
	}

	/**
	 * @param AssocArray $title
	 */
	static public function fromGraphqlNode(array $title) : Title {
// dump($title);
		$filterPeople = function(string $role, array $edges) : array {
			$people = [];
			foreach ($edges as $edge) {
				if ($edge['node']['category']['categoryId'] == $role) {
					$people[] = Person::fromGraphqlNode($edge['node']['name']);
				}
			}
			return $people;
		};

		return new static(
			$title['id'],
			$title['titleText']['text'],
			originalName: $title['originalTitleText']['text'] ?? null,
			type: self::typeFromTitleType($title['titleType']['id'] ?? ''),
			// series: empty($title['series']['series']) ? null : self::fromGraphqlNode($title['series']['series']),
			episode: empty($title['series']['episodeNumber']) ? null : Episode::fromGraphqlNode($title['series']),
			episodes: empty($title['episodes']['episodes']['edges']) ? [] : array_map(function(array $info) {
				return self::fromGraphqlNode($info['node']);
			}, $title['episodes']['episodes']['edges']),
			genres: array_map(fn(array $node) => $node['genre']['text'], $title['titleGenres']['genres'] ?? []),
			year: $title['releaseYear']['year'] ?? null,
			endYear: $title['releaseYear']['endYear'] ?? null,
			plot: $title['plots']['edges'][0]['node']['plotText']['plainText'] ?? null,
			duration: $title['runtime']['seconds'] ?? null,
			rating: $title['ratingsSummary']['aggregateRating'] ?? null,
			ratings: $title['ratingsSummary']['voteCount'] ?? null,
			userRating: array_key_exists('userRating', $title) ? TitleRating::fromGraphqlRating($title['id'], $title['userRating']) : null,
			image: Image::fromGraphql($title['primaryImage'] ?? []),
			actors: isset($title['principalCredits'])
				? Actor::fromGraphqlPrincipalCredits($title['principalCredits'][0]['credits'] ?? [])
				: Actor::fromGraphqlTitleCredits($title['creditsV2']['edges'] ?? []),
			directors: $filterPeople('director', $title['directors_writers']['edges'] ?? []),
			writers: $filterPeople('writer', $title['directors_writers']['edges'] ?? []),
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
				return (int) $match[1];
			}
		}
		return null;
	}

}
