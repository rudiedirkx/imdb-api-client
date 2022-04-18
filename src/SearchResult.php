<?php

namespace rdx\imdb;

interface SearchResult {

	// public $id;
	// public $name;

	public function getSearchResult() : string;

	public function getUrl() : string;

}
