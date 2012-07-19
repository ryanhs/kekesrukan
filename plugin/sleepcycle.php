<?php

// add this to onJobFree, $client->onJobFree($sleepcycle);
$sleepcycle = function($isJobFree = true){
	static $seq = 0;
	if($isJobFree){
		//echo "seq ({$seq})" . PHP_EOL;
		if($seq > 99)
			time_nanosleep(1, 0);
		else
			time_nanosleep(0, 10000000 * $seq);
			
		$seq++;
		
		if($seq > 1000)
			$seq = 0;
	}else
		$seq = 0;
};