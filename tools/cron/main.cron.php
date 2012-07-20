<?php
require '../../kekesrukan_client.php';

if(pcntl_fork()) exit;

$cron = array();
$client = new kekesrukan_client();

function addCron($interval, $callback){
	global $cron;
	$cron[] = array(
		'interval' => $interval,
		'callback' => $callback,
		'tick' => time(),
	);
}

$dir = dir(__DIR__ . "/include");
while (($file = $dir->read()) !== false)
	if($file != '.' && $file != '..')
		require __DIR__ . "/include/" . $file;
$dir->close();


init('cron');

while(true){
	$now = time();
	foreach($cron as $k => $job){
		if($job['interval'] + $job['tick'] == $now){
			call_user_func($job['callback']);
			$cron[$k]['tick'] = $now;
		}
	}
	time_nanosleep(0, 300000000);
}
