<?php

require 'inc.bootstrap.php';

if (IMDB_USER_ID) {
	$client->setAccount(IMDB_USER_ID);
}

$ratings = $client->getTitleRatingsMeta();

dump($ratings);
dump($client->watchlist);
