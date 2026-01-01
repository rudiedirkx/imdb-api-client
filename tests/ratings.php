<?php

require 'inc.bootstrap.php';

// dump($client->getTitleRatingsMeta());

dump(count($client->getRatedTitles()));
dump($client->ratedlist);

dump($client->_requests);
