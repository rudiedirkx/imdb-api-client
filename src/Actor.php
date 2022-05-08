<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class Actor {

	public function __construct(
		public ?Person $person,
		public ?Character $character,
		public ?Title $title = null,
	) {}

	static public function fromGraphqlPersonCredits(array $credits) : array {
		return array_values(array_filter(array_map(function($node) {
			return empty($node['node']['characters']) ? null : new static(
				null,
				new Character($node['node']['characters'][0]['name']),
				Title::fromGraphqlNode($node['node']['title']),
			);
		}, $credits)));
	}

	static public function fromGraphqlTitleCredits(array $credits) : array {
		return array_values(array_filter(array_map(function($node) {
			return empty($node['node']['characters']) ? null : new static(
				new Person($node['node']['name']['id'], $node['node']['name']['nameText']['text']),
				new Character($node['node']['characters'][0]['name']),
			);
		}, $credits)));
	}

	static public function fromTitleDocument(Node $doc) : array {
		$actors = $doc->queryAll('[data-testid="title-cast-item"]');
		$actors = array_map(function($el) {
			$person = $el->query('a[data-testid="title-cast-item__actor"]');
			$character = $el->query('.title-cast-item__characters-list a > span:first-child');
			return new static(
				new Person(Person::idFromHref($person['href']), $person->textContent),
				new Character(preg_replace('#^as #', '', $character->textContent))
			);
		}, $actors);
		return $actors;
	}

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
