<?php

abstract class abstractKekesrukan_client{
	protected $socket;
	protected $function;
	protected $job; // array(funcname, param, status)
	protected $callback; // array(funcname, param, status)
	protected $run;
	
	// basic
	public abstract function __construct($ip, $port);
	public abstract function registerFunction($function, $callback); // function($param)
	public abstract function sendJob($function, $param);
	public abstract function start();
	public abstract function stop();
	
	// event
	public abstract function onJobFree($callback);
}

/* ================================================================ */

class kekesrukan_client extends abstractKekesrukan_client{
	protected $config;
	protected $socket;
	protected $buffer;
	protected $function;
	protected $callback;
	protected $job;
	protected $run;
	
	public function __construct($ip = '127.0.0.1', $port = 14025){
		$this->config = array(
			'ip' => $ip,
			'port' => $port,
		);
		
		$this->socket = null;
		$this->buffer = '';
		$this->run = false;
		$this->function = array();
		$this->job = array();
		$this->callback = array(
			'onJobFree' => array(),
		);
		
		
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if(socket_connect($this->socket, $this->config['ip'], $this->config['port']) === false)
			exit("Error on connecting to [{$this->config['ip']}:{$this->config['port']}]\n");
	}
	
	public function registerFunction($function, $callback){
		if(!isset($this->function[$function])){
			$this->function[$function] = array(
				'callback' => array(),
			);
		}
		
		$this->function[$function]['callback'][] = $callback;
		$this->sendData('register', array(
			'function' => $function,
		));
	}
	
	public function sendJob($function, $param){
		$this->sendData('job', array(
			'function' => $function,
			'param' => $param,
		));
	}
	
	public function onJobFree($callback){
		$this->callback['onJobFree'][] = $callback;
	}
	
	public function sendData($menu, $param = array()){
		$data = base64_encode(serialize(array(
			'menu' => $menu,
			'param' => $param,
		))) . ' ';
		
		$write_data = socket_write($this->socket, $data);
		if($write_data === false){
			socket_close($socket);
			exit("Error on: writing socket\n");
		}
	}
	
	public function stop(){
		$this->run = false;
	}
	
	public function start(){
		$this->run = true;
		while($this->run){
			$isFreeTime = true;
			$isDataAvailable = socket_select($read = array($this->socket), $write = null, $except = null, 0);
			if($isDataAvailable === false){
				socket_close($this->socket);
				exit("Error on: selecting\n");
			}
			
			if($isDataAvailable > 0){
				$isFreeTime = false;
				$respond = socket_read($this->socket, 1024, PHP_BINARY_READ);
				if($respond === false || $respond == ''){
					socket_close($socket);
					exit("Error on: reading socket\n");
				}
				
				$this->buffer .= $respond;
				$buf = explode(' ', $this->buffer);
				$this->buffer = '';
				foreach($buf as $line){
					if(strlen($line) < 2) continue;
					
					$data = unserialize(base64_decode($line));
					if(!is_array($data)){
						if($line == $buf[count($buf) - 1]){
							$this->buffer = $line;
						}
						continue;
					}else{
						if(!empty($this->function[$data['function']])){
							$this->job[$data['uniqId']] = array(
								'function' => $data['function'],
								'param' => $data['param'],
								'status' => 'received',
							);
							$worked = false;
							foreach($this->function[$data['function']]['callback'] as $callback){
								$callback($data['param']);
								$worked = true;
							}
							if($worked){
								$this->sendData('done', array(
									'uniqId' => $data['uniqId'],
									'function' => $data['function'],
								));
								unset($this->job[$data['uniqId']]);
							}
						}
					}
				}
			}else{
				foreach($this->job as $k => $job){
					if($job['status'] == 'received'){
						if(!empty($this->function[$job['function']])){
							$worked = false;
							foreach($this->function[$job['function']]['callback'] as $callback){
								$callback($job['param']);
								$this->job[$k]['status'] = 'worked';
								$worked = true;
							}
							if($worked){
								$this->sendData('done', array(
									'uniqId' => $k,
									'function' => $job['function'],
								));
								unset($this->job[$k]);
								$isFreeTime = false;
							}
						}
					}
				}
			}
			
			
			foreach($this->callback['onJobFree'] as $callback){
				$callback($isFreeTime);
			}
		}
		
		socket_close($this->socket);
	}
}