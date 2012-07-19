<?php

abstract class abstractKekesrukan_server{
	
	protected $socket;
	protected $function; // array(funcname, clientId, callback)
	protected $client; // array(socket)
	protected $job;
	protected $callback;
	protected $run;
	
	public abstract function onFunctionRegisted($callback);
	public abstract function onFunctionCalled($callback);
	public abstract function onFunctionDone($callback);
	
	public abstract function onJobFree($callback);
	
	public abstract function start();
	public abstract function stop();
}

/* ================================================================ */

class kekesrukan_server extends abstractKekesrukan_server{
	protected $config;
	protected $socket;
	protected $function;
	protected $client;
	protected $job;
	protected $run;
	protected $callback;
	
	public function __construct($ip = '127.0.0.1', $port = 14025){
		$this->config = array(
			'ip' => $ip,
			'port' => $port,
		);
		
		$this->socket = null;
		$this->function = array();
		$this->client = array();
		$this->job = array();
		$this->run = false;
		$this->callback = array(
			'onFunctionRegisted' => array(),
			'onFunctionCalled' => array(),
			'onFunctionDone' => array(),
			'onJobFree' => array(),
		);
	}
	
	private function createFunction($funcname){
		if(!isset($this->function[$funcname]))
			$this->function[$funcname] = array(
				'client' => null,
			);
	}
	
	public function onFunctionRegisted($callback){
		$this->callback['onFunctionRegisted'][] = $callback;
	}
	
	public function onFunctionCalled($callback){
		$this->callback['onFunctionCalled'][] = $callback;
	}
	
	public function onFunctionDone($callback){
		$this->callback['onFunctionDone'][] = $callback;
	}
	
	public function onJobFree($callback){
		$this->callback['onJobFree'][] = $callback;
	}
	
	
	private function getClientSocketArray(){
		$return = array();
		foreach($this->client as $client){
			$return[] = $client['socket'];
		}
		return $return;
	}
	
	private function getClientBySocket($socket){
		foreach($this->client as $key => $client){
			if($client['socket'] == $socket)
				return $key;
		}
		return null;
	}
	
	private function readsocket($socket){
		$rtn = @socket_read($socket, 1024, PHP_BINARY_READ);
		if($rtn === false || $rtn == '')
			return false;
		return $rtn;
	}
	
	private function writesocket($socket, $data_write){
		$rtn = @socket_write($socket, base64_encode(serialize($data_write)) . ' ');
		if($rtn === false)
			return false;
		return $rtn;
	}
	
	private function clientHandle($key){
		$buf = $this->client[$key]['buffer'];
		$this->client[$key]['buffer'] = '';
		$buf = explode(' ', $buf);
		foreach($buf as $line){
			if(strlen($line) < 2) continue;
			
			$data = unserialize(base64_decode($line));
			if(!is_array($data)){
				if($line == $buf[count($buf) - 1]){
					$this->client[$key]['buffer'] = $line;
					break;
				}
			}else{
				
				//$data = array
				switch($data['menu']){
					case 'register':
						$this->createFunction($data['param']['function']);
						$this->function[$data['param']['function']]['client'] = $key;
						if(count($this->callback['onFunctionRegisted']) > 0){
							$time = date('Y-m-d H:i:s');
							foreach($this->callback['onFunctionRegisted'] as $callback){
								$callback(array(
									'function' => $data['param']['function'],
									'time' => $time,
								));
							}
						}
						break;
					case 'job':
						$uniqId = uniqid();
						$this->job[$uniqId] = array(
							'function' => $data['param']['function'],
							'param' => $data['param']['param'],
							'status' => 'open',
						);
						
						$this->createFunction($data['param']['function']);
						$clientId = $this->function[$data['param']['function']]['client'];
						if($clientId !== null){
							$write = $this->writesocket($this->client[$clientId]['socket'], array(
								'uniqId' => $uniqId,
								'function' => $data['param']['function'],
								'param' => $data['param']['param'],
							));
							if($write === false){
								$this->clientClose($clientId);
								break;
							}
							$this->job[$uniqId]['status'] = 'sent';
							if(count($this->callback['onFunctionCalled']) > 0){
								$time = date('Y-m-d H:i:s');
								foreach($this->callback['onFunctionCalled'] as $callback){
									$callback(array(
										'function' => $data['param']['function'],
										'time' => $time,
										'uniqId' => $uniqId,
										'param' => $data['param']['param'],
									));
								}
							}
						}
						
						break;
					case 'done':
						foreach($this->job as $k => $job){
							if($k == $data['param']['uniqId']){
								$this->job[$k]['status'] = 'finish';
								if(count($this->callback['onFunctionDone']) > 0){
									$time = date('Y-m-d H:i:s');
									foreach($this->callback['onFunctionDone'] as $callback){
										$callback(array(
											'function' => $data['param']['function'],
											'time' => $time,
											'uniqId' => $data['param']['uniqId'],
											'param' => $this->job[$data['param']['uniqId']]['param'],
										));
									}
									
								}
								unset($this->job[$k]);
								break;
							}
						}
						break;
				}
			}
		}
		
	}
	
	private function clientClose($key){
		if(isset($this->client[$key])){
			$client = $this->client[$key];
			foreach($this->function as $k => $function){
				if($function['client'] == $client['socket']){
					$this->function[$k]['client'] = null;
					unset($this->client[$key]);
					break;
				}
			}
		}
	}
	
	public function stop(){
		$this->run = false;
	}
	
	public function start(){
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
		if(!socket_bind($this->socket, $this->config['ip'], $this->config['port']))
			return false;
		if(!socket_listen($this->socket, 16))
			return false;
		socket_set_nonblock($this->socket);
		
		$this->run = true;
		while($this->run){
			/*
			$stat_open = 0;
			$stat_sent = 0;
			$stat_finish = 0;
			$stat_total = 0;
			foreach($this->job as $job){
				if($job['status'] == 'open')
					$stat_open++;
				elseif($job['status'] == 'sent')
					$stat_sent++;
				elseif($job['status'] == 'finish')
					$stat_finish++;
			}
			echo 'job total: ' . $stat_open + $stat_sent + $stat_finish;
			echo ', job open: ' . $stat_open;
			echo ', job sent: ' . $stat_sent;
			echo ', job finish: ' . $stat_finish . PHP_EOL;
			*/
			
			$isFreeTime = true;
			$read = array_merge($this->getClientSocketArray(), array($this->socket));
			$read_num = socket_select($read, $write = NULL, $except = NULL, 0);
			if($read_num < 0){
				$this->run = false;
				return false;
			}
			if(count($read) > 0){
				foreach($read as $socket){
					if($socket == $this->socket){
						$this->client[] = array(
							'socket' => socket_accept($this->socket),
							'buffer' => '',
						);
						$isFreeTime = false;
						//echo "free time interrupted, accepting new socket" . PHP_EOL;
						continue;
					}
					
					$key = $this->getClientBySocket($socket);
					
					$buf = $this->readsocket($socket);
					if($buf === false || $buf == ''){
						$this->clientClose($key);
						continue;
					}
					
					$this->client[$key]['buffer'] .= $buf;
					$this->clientHandle($key);
					$isFreeTime = false;
					//echo "free time interrupted, reading client" . PHP_EOL;
					/*
					if(@socket_write($this->client[$key]['socket'], $this->client[$key]['buffer']) === false){
						unset($this->client[$key]);
					}
					*/
				}
			}
			
			
			foreach($this->job as $k => $job){
				if($job['status'] == 'open'){
					$clientId = $this->function[$job['function']]['client'];
					if($clientId !== null){
						$write = $this->writesocket($this->client[$clientId]['socket'], array(
								'uniqId' => $k,
								'function' => $job['function'],
								'param' => $job['param'],
						));
						if($write === false){
							$this->clientClose($clientId);
							break;
						}
						$this->job[$k]['status'] = 'sent';
						if(count($this->callback['onFunctionCalled']) > 0){
							$time = date('Y-m-d H:i:s');
							foreach($this->callback['onFunctionCalled'] as $callback){
								$callback(array(
									'function' => $job['function'],
									'time' => $time,
									'uniqId' => $k,
									'param' => $job['param'],
								));
							}
						}
						$isFreeTime = false;
						//echo "free time intrrupted, unfinished job" . PHP_EOL;
					}
				}
			}
			
			foreach($this->callback['onJobFree'] as $callback){
				$callback($isFreeTime);
			}
		}
		
		socket_close($this->socket);
		return true;
	}
}