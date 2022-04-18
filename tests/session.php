<?php

require 'inc.bootstrap.php';

echo 'watchlist: ';
var_dump($client->watchlist);

echo '_requests: ';
print_r($client->_requests);
