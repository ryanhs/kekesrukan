<?php
ini_set('max_execution_time', 0);

require '../kekesrukan.worker.php';

$worker = new kekesrukan_worker();

$param = serialize(array(
	'orderId' => rand(0, 999),
	'callback' => 'goodCharlot',
));

$worker->sendTask('funcTest', $param);

// wait until callback called
while(($newTask = $worker->recvTask('funcTest', $param)) === null) sleep(1);

echo $newTask['functionName'] . ' : ' . $newTask['param'] . PHP_EOL;
