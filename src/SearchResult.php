<?php

namespace rdx\imdb;

class SearchResult {

	public $id;
	public $name;
	public $info;

	public function __construct(string $id, string $name, string $info) {
		$this->id = $id;
		$this->name = $name;
		$this->info = $info;
	}

	public function __toString() {
		return "[?] $this->name ($this->info) [$this->id]";
	}

	static public function fromJsonSearchItem(array $item) : self {
		return new static($item['id'], $item['l'], $item['s']);
	}

	static public function fromJsonSearch(array $item) : ?self {
		if (preg_match('#^tt\d+#', $item['id'])) {
			return SearchResultTitle::fromJsonSearchItem($item);
			// return (new SearchResultTitle($item['id'], $item['l'], $item['s']))->setActors($item['s'])->setRank($item['rank']);
		}
		elseif (preg_match('#^nm\d+#', $item['id'])) {
			return SearchResultPerson::fromJsonSearchItem($item);
			// return (new SearchResultPerson($item['id'], $item['l'], $item['s']))->setActors($item['s'])->setRank($item['rank']);
		}
		elseif (isset($item['l'], $item['s'])) {
			return new SearchResult($item['id'], $item['l'], $item['s']);
		}
		return null;
	}

}
