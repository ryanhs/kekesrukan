<?php
addCron(5 * 60, function(){
	global $client;
	$client->sendJob('ym_rawsend', serialize(array(
		'to' => 'dr.web123',
		'msg' => 'cron every 5 minutes, time#' . time(),
	)));
	//echo 'test #' . time() . PHP_EOL;
});