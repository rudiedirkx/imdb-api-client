<?php

namespace rdx\imdb;

/**
 * @property-read ?Image $image
 */
interface SearchResult {

	// public $id;
	// public $name;

	public function getSearchInfo() : string;

	public function getSearchResult() : string;

	public function getUrl() : string;

}
