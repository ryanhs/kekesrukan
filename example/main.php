<?php
ini_set('max_execution_time', 0);

require '../kekesrukan.main.php';

$server = new kekesrukan_main();

$server->onTaskReceived(function($functionName, $param) use($server) {
	echo "========================" . PHP_EOL;
	echo "Task : " . $functionName . PHP_EOL;
	echo "Param: " . PHP_EOL;
	
	$param = unserialize($param);
	
	var_dump($param);
	
	$server->sendTask($param['callback'], 'ok');
});

$server->start();