<?php

require '../kekesrukan_server.php';
require '../plugin/sleepcycle.php';

$server = new kekesrukan_server();
/*
$server->onFunctionRegisted(function($param){
	echo "[{$param['time']}] {$param['function']} registered" . PHP_EOL;
});

$server->onFunctionCalled(function($param){
	echo "[{$param['time']}] {$param['function']}('{$param['param']}') called#id:{$param['uniqId']}" . PHP_EOL;
});

$server->onFunctionDone(function($param){
	echo "[{$param['time']}] {$param['function']}('{$param['param']}') done#id:{$param['uniqId']}" . PHP_EOL;
});
*/
$server->onJobFree($sleepcycle);

//if(pcntl_fork()) exit;
$server->start();