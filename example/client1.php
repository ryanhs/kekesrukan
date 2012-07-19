<?php

require '../kekesrukan_client.php';
require '../plugin/sleepcycle.php';

$client = new kekesrukan_client();

$client->registerFunction('finish', function($param){
	//echo "helloworld({$param}) finish" . PHP_EOL;
});

$seq = 0;
$client->onJobFree(function(){
	global $client, $seq;
	//echo "free time on seq({$seq})" . PHP_EOL;
	
	$param = array(
		'param' => 'testing ' . $seq,
		'callback' => 'finish',
	);
	$client->sendJob('helloworld', serialize($param));
	//echo "helloworld({$param['param']}) calling" . PHP_EOL;
	$seq++;
	
	time_nanosleep(0, 300000000);
});

$client->start();