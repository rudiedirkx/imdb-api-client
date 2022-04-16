<?php

require 'inc.bootstrap.php';

if ( $client->authNeedsLogin() ) {
	var_dump($client->logIn());
}

var_dump($client->checkSession());
var_dump($client->watchlist);

print_r($client->_requests);
