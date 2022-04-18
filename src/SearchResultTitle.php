<?php

namespace rdx\imdb;

class SearchResultTitle extends SearchResult {

	public $year;
	public $rank;

	public function __construct(string $id, string $name, string $info, ?int $year = null, ?int $rank = null) {
		parent::__construct($id, $name, $info);
		$this->year = $year;
		$this->rank = $rank;
	}

	public function __toString() {
		return "[TITLE] $this->name ($this->year) ($this->info) [$this->id]";
	}

	static public function fromJsonSearchItem(array $item) : self {
		return new static($item['id'], $item['l'], $item['s'], $item['y'] ?? null, $item['rank'] ?? null);
	}

}
