<?php

require_once 'search.php';
require_once 'SynoPluginMock.php';

$domain = 'https://www.cpasbien.ac';
$qurl = '/recherche/';
$curl = curl_init();
$query = 'walking dead';
$url = $domain . $qurl . urlencode($query);
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec($curl);
$dlm = new SynoDLMSearchOxTorrent();
$dlm->parse(new SynoPluginMock(), $response);


