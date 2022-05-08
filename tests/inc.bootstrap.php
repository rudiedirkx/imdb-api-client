<?php

use rdx\imdb\Client;
use rdx\imdb\AuthSession;
use rdx\imdb\AuthWeb;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../env.php';

header('Content-type: text/plain; charset=utf-8');

$client = new Client(
	// new AuthWeb(IMDB_USER, IMDB_PASS)
	new AuthSession(IMDB_AT_MAIN, IMDB_UBID_MAIN)
);

$loggedIn = $client->logIn();
if (empty($_quiet)) {
	echo 'logIn: ';
	var_dump($loggedIn);
}
