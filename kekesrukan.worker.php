<?php

class kekesrukan_worker{
	
	protected $ip;
	protected $port;

	protected $connected;
	protected $socket;
	
	public function __construct($ip = '127.0.0.1', $port = 14025){
		$this->ip = $ip;
		$this->port = $port;
		
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$this->connected = socket_connect($this->socket, $this->ip, $this->port);
	}
	
	public function __destruct(){
		if($this->connected){
			socket_close($this->socket);
			$this->connected = false;
		}
	}
	
	public function isConnected(){
		return $this->connected;
	}
	
	public function getSocketStream(){
		return $this->socket;
	}
	
	public function recvTask(){
		if($this->connected){
			$c = socket_select($r = array($this->socket), $w = NULL, $e = NULL, 0);
			if($c > 0){
				$data = socket_read ($this->socket, 4096, PHP_BINARY_READ);
				return unserialize($data);
			}
		}
		return null;
	}
	
	public function sendTask($function, $param){
		if($this->connected){
			$task = serialize(array(
				'function' => $function,
				'param' => $param,
			));
			if(socket_write($this->socket, $task, strlen($task)) === false){
				$this->connected = false;
			}else
				return true;
		}
		return false;
	}
}