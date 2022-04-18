<?php

require 'inc.bootstrap.php';

echo '"anna k": ';
$results = $client->search('anna k');
print_r(array_map('strval', $results));

echo '"dumb & dumber": ';
$results = $client->search('dumb & dumber');
print_r(array_map('strval', $results));

print_r($client->_requests);
