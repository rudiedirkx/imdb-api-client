<?php

namespace rdx\imdb;

interface SearchResult {

	// public $id;
	// public $name;

	public function getSearchInfo() : string;

	public function getSearchResult() : string;

	public function getUrl() : string;

}
