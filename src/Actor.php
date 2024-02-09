<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class Actor {

	public function __construct(
		public ?Person $person,
		public ?Character $character,
		public ?Title $title,
		public ?string $source = null,
	) {}

	static protected function fromGraphqlPerson(array $node) : ?static {
		if (!empty($node['summary']['principalCharacters'])) {
			return new static(
				null,
				new Character($node['summary']['principalCharacters'][0]['name']),
				Title::fromGraphqlNode($node['title']),
			);
		}

		if (!empty($node['characters'])) {
			return new static(
				null,
				new Character($node['characters'][0]['name']),
				Title::fromGraphqlNode($node['title']),
			);
		}

		if (!empty($node['title'])) {
			return new static(
				null,
				null,
				Title::fromGraphqlNode($node['title']),
			);
		}

		return null;
	}

	static public function fromGraphqlPersonCreditsAndKnownFor(array $credits, array $knownFor) : array {
		$actors = [];

		foreach ($credits as $edge) {
			if ($actor = static::fromGraphqlPerson($edge['node'])) {
				$actor->source = 'credits';
				$actors[$actor->title->id] = $actor;
			}
		}

		foreach ($knownFor as $edge) {
			if ($actor = static::fromGraphqlPerson($edge['node'])) {
				$actor->source = 'knownFor';
				$actors[$actor->title->id] = $actor;
			}
		}

		usort($actors, fn($a, $b) => $b->title->year <=> $a->title->year);

		return $actors;
	}

	static public function fromGraphqlTitleCredits(array $credits) : array {
		return array_values(array_filter(array_map(function($node) {
			return new static(
				Person::fromGraphqlNode($node['name'] ?? $node['node']['name']),
				new Character($node['node']['characters'][0]['name'] ?? '?'),
				null,
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
				new Character(preg_replace('#^as #', '', $character->textContent)),
				null,
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
				new Character($charlink ? $charlink->textContent : $cells[3]->textContent),
				null,
			);
		}

		return $actors;
	}

}
