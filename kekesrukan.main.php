<?php


class kekesrukan_main{
	
	protected $ip;
	protected $port;
	
	protected $connected;
	protected $socket;
	protected $socket_read;
	
	protected $onTaskReceived_listener;
	protected $queueTask;
	protected $stop;

	public function __construct($ip = '127.0.0.1', $port = 14025){
		$this->ip = $ip;
		$this->port = $port;
		$this->connected = false;
		$this->socket = null;
		$this->socket_read = array();
		$this->onTaskReceived_listener = array();
		$this->queueTask = array();
		$this->stop = false;
		
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
		if(socket_bind($this->socket, $this->ip, $this->port)){
			if(socket_listen($this->socket, 1024)){
				socket_set_nonblock($this->socket);
				$this->socket_read = array($this->socket);
				$this->connected = true;
			}
		}
	}
	
	// auto stop if not stopeed
	public function __destruct(){
		$this->stop();
	}
	
	public function isConnected(){
		return $this->connected;
	}
	
	public function getSocketStream(){
		return $this->socket;
	}
	
	// run main loop
	public function start(){
		while($this->stop == false){
			if(count($this->queueTask) > 0){
				foreach($this->queueTask as $taskKey => $task){
					//broadcast new task
					foreach($this->socket_read as $socketKey => $socket){
						if($socket != $this->socket){
							$dataToWrite = serialize($task);
							if(socket_write($socket, $dataToWrite, strlen($dataToWrite)) === false){
								socket_close($socket);
								unset($this->socket_read[$socketKey]);
							}
						}
					}
					unset($this->queueTask[$taskKey]);
				}
			}
			
			// ----------------------------------------------------------------
			$read = $this->socket_read;
			$read_num = socket_select($read, $write = NULL, $except = NULL, 0);
			if($read_num > 0){
				foreach($read as $key => $socket){
					// if there's any client trying to connect
					if($this->socket == $socket){
						$this->socket_read[] = socket_accept($this->socket);
						continue;
					}
					
					$data = socket_read($socket, 512, PHP_BINARY_READ);
					
					// if data received 0 bytes
					if(strlen($data) == 0){
						socket_close($socket);
						unset($this->socket_read[$key]);
						continue;
					}
					
					$data = unserialize($data);
					
					foreach($this->onTaskReceived_listener as $listener){
						$listener($data['function'], $data['param']);
					}
				}
			}
			sleep(1);
		}
	}
	
	public function stop(){
		if($this->connected){
			socket_close($this->socket);
			$this->connected = false;
			$this->stop = true;
		}
	}
	
	
	public function onTaskReceived($function){
		$this->onTaskReceived_listener[] = $function;
	}
	
	public function sendTask($functionName, $param){
		$this->queueTask[] = array(
			'functionName' => $functionName,
			'param' => $param,
		);
	}
	
	
	
	
}