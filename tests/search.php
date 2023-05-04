<?php

require 'inc.bootstrap.php';

echo '"anna k": ';
$results = $client->searchGraphql('anna k');
print_r($results);

// echo '"anna k": ';
// $results = $client->searchTitles('anna k');
// print_r($results);

// echo '"dumb & dumber": ';
// $results = $client->searchPeople('dumb');
// print_r($results);

print_r($client->_requests);
