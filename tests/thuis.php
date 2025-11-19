<?php

require 'inc.bootstrap.php';

if (IMDB_USER_ID) {
	$client->setAccount(IMDB_USER_ID);
}

dump($client->watchlist);

$ratings = $client->getTitleRatingsMeta();
dump($ratings);
