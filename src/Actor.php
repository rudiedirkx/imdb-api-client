<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class Actor {

	public function __construct(
		public Person $person,
		public Character $character,
	) {}

	static public function fromCreditsDocument(Node $doc) : array {
		$rows = $doc->queryAll('table.cast_list tr');

		$actors = [];
		foreach ($rows as $tr) {
			$cells = $tr->children();
			$link = $tr->query('a[href^="/name/"]');
			if (count($cells) != 4 || !$link) continue;

			$charlink = $cells[3]->query('a[href^="/title/"]');

			$actors[] = new static(
				new Person(Person::idFromHref($link['href']), $cells[1]->textContent),
				new Character($charlink ? $charlink->textContent : $cells[3]->textContent)
			);
		}

		return $actors;
	}

}
