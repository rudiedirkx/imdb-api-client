<?php

require 'inc.bootstrap.php';

// dump($client->getTitleRatingsMeta());

dump(count($client->getTitleRatings()));
dump($client->ratedlist);

dump($client->_requests);
