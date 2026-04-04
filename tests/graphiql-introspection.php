<?php

use rdx\imdb\GraphqlIntrospectionCrawler;

$_login = false;
require 'inc.bootstrap.php';
header('Content-type: text/html; charset=utf-8');

$crawler = new GraphqlIntrospectionCrawler(
	__DIR__ . '/introspection.cache',
	pager: intval(getenv('TYPES_PAGER')) ?: 3,
);
// dd($crawler);
foreach ($crawler->crawl() as $todo => $names) {
	foreach ($names as $name) {
		echo "$name ($todo left)\n";
	}

	if ($todo > 0) {
		sleep(2);
		usleep(rand(0, 3000));
	}
}
// dd($crawler);
