<?php

require 'inc.bootstrap.php';

$rating = rand(1, 9);
echo "tt12055180 = $rating: ";
var_dump($client->rateTitle('tt12055180', $rating));

print_r($client->_requests);
