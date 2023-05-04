<?php

require 'inc.bootstrap.php';

echo "title: ";
var_dump($client->getGraphqlTitle('tt123123123'));

echo "person: ";
var_dump($client->getGraphqlPerson('nm123123123'));

print_r($client->_requests);
