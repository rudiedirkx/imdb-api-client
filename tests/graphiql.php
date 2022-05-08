<?php

$_quiet = true;
require 'inc.bootstrap.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$rsp = $client->graphql($data['query'], $data['variables'] ?? []);
$json = (string) $rsp->getBody();
$data = json_decode($json, true);
unset($data['extensions']['disclaimer']);

header('Content-type: application/json; charset=utf-8');
echo json_encode($data);
