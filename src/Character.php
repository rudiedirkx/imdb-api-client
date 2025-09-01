<?php

namespace rdx\imdb;

class Character {

	public const DELIM = ' // ';
	private const SHOW = 2;

	public function __construct(
		public string $name,
	) {}

	public function getMaxedNames() : string {
		$names = explode(self::DELIM, $this->name);
		if (count($names) <= self::SHOW) {
			return $this->name;
		}

		return implode(self::DELIM, array_slice($names, 0, self::SHOW)) . ' + ' . (count($names) - self::SHOW);
	}

	public function getFullIfMaxedNames() : string {
		$names = explode(self::DELIM, $this->name);
		if (count($names) <= self::SHOW) {
			return '';
		}

		return $this->name;
	}

}
