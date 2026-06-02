<?php

use rdx\imdb\GraphqlIntrospectionCrawler;

$_quiet = true;
require 'inc.bootstrap.php';

$json = file_get_contents('php://input');
$input = json_decode($json, true);

if (!empty($_GET['introspection']) || str_starts_with(trim($input['query']), 'query IntrospectionQuery')) {
	header('Content-type: application/json; charset=utf-8');

	$crawler = new GraphqlIntrospectionCrawler(__DIR__ . '/introspection.cache');

	echo json_encode([
		'data' => [
			'__schema' => $crawler->getSchema(),
		],
	]);
	exit;
}

// Remove all lines starting with #
$input['query'] = trim(implode("\n", array_filter(explode("\n", $input['query']), function($line) {
	return !str_starts_with(trim($line), '#');
})));

$rsp = $client->graphql($input['query'], $input['variables'] ?? []);
$json = (string) $rsp->getBody();
$output = json_decode($json, true);
unset($output['extensions']['disclaimer']);

header('Content-type: application/json; charset=utf-8');
echo json_encode($output);
