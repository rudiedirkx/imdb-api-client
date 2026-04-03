<?php

use rdx\imdb\GraphqlIntrospectionCrawler;

$_login = false;
require 'inc.bootstrap.php';
header('Content-type: text/html; charset=utf-8');

$crawler = new GraphqlIntrospectionCrawler(__DIR__ . '/introspection.cache');
// dd($crawler);
foreach ($crawler->crawl() as $todo => $names) {
	foreach ($names as $name) {
		echo "$name ($todo left)\n";
	}

	sleep(2);
	usleep(rand(0, 3000));
}
dd($crawler);
