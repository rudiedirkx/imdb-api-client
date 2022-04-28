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

print_r($client->inWatchlists([$id, 'tt0086197', 'tt4263482', 'tt8772296']));

print_r($client->_requests);
