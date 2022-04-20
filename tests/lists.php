<?php

require 'inc.bootstrap.php';

print_r($client->inWatchlists(['tt0086197', 'tt4263482', 'tt8772296']));

// print_r($client->getLists());
// print_r($client->watchlist);

print_r($client->_requests);
