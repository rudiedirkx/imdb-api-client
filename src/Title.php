<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class Title {

	public $id;
	public $name;
	public $year;
	public $description;

	public function __construct(string $id, string $name, int $year, string $description) {
		$this->id = $id;
		$this->name = $name;
		$this->year = $year;
		$this->description = $description;
	}

	static public function fromDocument(string $id, Node $doc) {
		$h1 = $doc->query('h1');
		$desc = $doc->query('[data-testid="plot-xl"]');
		$year = static::getYear($id, $doc);

		return new static($id, trim($h1->textContent), $year, trim($desc->textContent));
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
