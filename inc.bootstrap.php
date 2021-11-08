<?php

use rdx\imdb\Client;
use rdx\imdb\ImdbSessionAuth;
use rdx\imdb\ImdbWebAuth;

require 'vendor/autoload.php';
require 'env.php';

header('Content-type: text/plain; charset=utf-8');

$client = new Client(
	// new ImdbWebAuth(IMDB_USER, IMDB_PASS)
	new ImdbSessionAuth(IMDB_AT_MAIN, IMDB_UBID_MAIN)
);
