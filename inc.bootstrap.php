<?php

use rdx\imdb\Client;
use rdx\imdb\ImdbWebAuth;

require 'vendor/autoload.php';
require 'env.php';

$client = new Client(new ImdbWebAuth(IMDB_USER, IMDB_PASS));
