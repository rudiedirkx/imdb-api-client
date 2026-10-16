<?php

use rdx\imdb\GraphqlIntrospectionCrawler;

$_login = false;
require 'inc.bootstrap.php';
header('Content-type: text/html; charset=utf-8');

$crawler = new GraphqlIntrospectionCrawler(
	__DIR__ . '/introspection.cache',
	pager: intval(getenv('TYPES_PAGER')) ?: 3,
);

$descriptions = !in_array('--no-comments', $argv ?? [], true);

if (in_array('--sdl', $argv ?? [], true)) {
	echo $crawler->getSDL($descriptions);
	exit;
}
if (in_array('--json', $argv ?? [], true)) {
	$result = ['data' => ['__schema' => $crawler->getSchema($descriptions)]];
	echo json_encode($result);
	exit;
}

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
