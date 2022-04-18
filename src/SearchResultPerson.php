<?php

namespace rdx\imdb;

class SearchResultPerson extends SearchResult {

	public function __toString() {
		return "[PERSON] $this->name ($this->info) [$this->id]";
	}

}
