<?php

require '../kekesrukan_client.php';
require '../plugin/sleepcycle.php';

$client = new kekesrukan_client();

$client->registerFunction('helloworld', function($param){
	global $client;
	$param = unserialize($param);
	//echo "helloworld({$param['param']}) called" . PHP_EOL;
	
	$client->sendJob($param['callback'], $param['param']);
});

$client->onJobFree($sleepcycle);

$client->start();