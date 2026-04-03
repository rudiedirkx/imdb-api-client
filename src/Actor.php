<?php

namespace rdx\imdb;

use rdx\jsdom\Node;

class Actor {

	public function __construct(
		public ?Person $person,
		public ?Character $character,
		public ?Title $title,
		public ?string $source = null,
		public ?int $fromYear = null,
		public ?int $toYear = null,
	) {}

	/**
	 * @param AssocArray $node
	 */
	static protected function fromCredit(array $node) : static {
		$title = isset($node['title']) ? Title::fromGraphqlNode($node['title']) : null;
		$person = isset($node['name']) ? Person::fromGraphqlNode($node['name']) : null;

		$charNames = [];
		foreach ($node['creditedRoles']['edges'] ?? [] as $edge2) {
			foreach ($edge2['node']['characters']['edges'] as $edge3) {
				$charNames[] = $edge3['node']['name'];
			}
		}
		$character = Character::fromNames($charNames);

		$fromYear = $node['episodeCredits']['yearRange']['year'] ?? null;
		$toYear = $node['episodeCredits']['yearRange']['endYear'] ?? null;

		return new static(
			person: $person,
			character: $character,
			title: $title,
			fromYear: $fromYear,
			toYear: $toYear,
		);
	}

	/**
	 * @param list<AssocArray> $credits
	 * @param list<AssocArray> $knownFor
	 * @return list<self>
	 */
	static public function fromGraphqlPersonCreditsAndKnownFor(array $credits, array $knownFor) : array {
		$actors = [];

		foreach ($credits as $edge) {
			$actor = static::fromCredit($edge['node']);
			$actor->source = 'credits';
			$actors[$actor->title->id] = $actor;
		}

		foreach ($knownFor as $node) {
			$actor = static::fromCredit($node);
			$actor->source = 'knownFor';
			$actors[$actor->title->id] = $actor;
		}

		usort($actors, fn($a, $b) => $b->title->year <=> $a->title->year);

		return $actors;
	}

	/**
	 * @param list<AssocArray> $credits
	 * @return list<Actor>
	 */
	static public function fromGraphqlTitleCredits(array $credits) : array {
		return array_map(function(array $edge) {
			return static::fromCredit($edge['node']);
		}, $credits);
	}

	/**
	 * @param list<AssocArray> $credits
	 * @return list<Actor>
	 */
	static public function fromGraphqlPrincipalCredits(array $credits) : array {
		return array_map(function(array $node) {
			return static::fromCredit($node);
		}, $credits);
	}

	/**
	 * @return list<self>
	 */
	static public function fromTitleDocument(Node $doc) : array {
		$actors = $doc->queryAll('[data-testid="title-cast-item"]');
		$actors = array_values(array_map(function($el) {
			$person = $el->query('a[data-testid="title-cast-item__actor"]');
			$character = $el->query('.title-cast-item__characters-list a > span:first-child');
			return new static(
				new Person(Person::idFromHref($person['href']), $person->textContent),
				new Character(preg_replace('#^as #', '', $character->textContent)),
				null,
			);
		}, $actors));
		return $actors;
	}

	/**
	 * @return list<self>
	 */
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
