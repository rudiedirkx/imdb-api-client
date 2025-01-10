<?php

namespace rdx\imdb;

class Image {

	public ?float $ratio = null;

	public function __construct(
		public string $url,
		public ?int $width = null,
		public ?int $height = null,
	) {
		if ($this->width && $this->height) {
			$this->ratio = $this->width / $this->height;
		}
	}

	public function getHeightForWidth(int $width) : ?int {
		return $this->ratio === null ? null : (int) round($width / $this->ratio);
	}

	public function getWidthForHeight(int $height) : ?int {
		return $this->ratio === null ? null : (int) round($height * $this->ratio);
	}

	/**
	 * @param AssocArray $node
	 */
	static public function fromGraphql(array $node) : ?Image {
		if (empty($node['url'])) return null;

		return new static(
			$node['url'],
			width: $node['width'] ?? null,
			height: $node['height'] ?? null,
		);
	}

	/**
	 * @param AssocArray $node
	 */
	static public function fromJsonSearch(array $node) : ?Image {
		if (empty($node['imageUrl'])) return null;

		return new static(
			$node['imageUrl'],
			width: $node['width'] ?? null,
			height: $node['height'] ?? null,
		);
	}

}
