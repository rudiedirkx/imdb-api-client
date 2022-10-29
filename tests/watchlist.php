<?php

require 'inc.bootstrap.php';

$id = 'tt12055180';
if (rand(0, 1)) {
	echo "add $id: ";
	var_dump($client->addTitleToWatchlist($id));
}
else {
	echo "remove $id: ";
	var_dump($client->removeTitleFromWatchlist($id));
}

$id2 = 'tt4263482';
echo "$id2 in watchlist: ";
var_dump($client->inWatchlist($id2));
// print_r($client->inWatchlists([$id, 'tt0086197', $id2, 'tt8772296']));

print_r($client->_requests);
