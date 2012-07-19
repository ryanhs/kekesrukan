<?php
class ngesrukConnect
{
    private $ip;
    private $port;
    private $fp;
    private $connected;
    private $error;
    private $msg;
    function __construct($ip, $port)
    {
        $this->ip        = $ip;
        $this->port      = $port;
        $this->fp        = null;
        $this->connected = false;
        $this->error     = '';
        $this->msg       = '';
    }
    function __destruct()
    {
        $this->disconnect();
    }
    function connect()
    {
        if ($this->connected == false)
        {
            $this->fp        = @fsockopen("tcp://{$this->ip}", (int) $this->port, $errno, $errstr);
            $this->connected = (!$this->fp) ? false : true;
            if (!$this->connected)
                $this->error = "ERROR: $errno - $errstr" . PHP_EOL;
        }
        return $this->connected;
    }
    function disconnect()
    {
        if ($this->connected)
            fclose($this->fp);
    }
    function isConnected()
    {
        return $this->connected;
    }
    function getLastError()
    {
        return $this->error;
    }
    function getLastMsg()
    {
        return $this->msg;
    }
    function send($msg)
    {
        if ($this->connected)
        {
            if (fwrite($this->fp, $msg . ' ') == false)
            {
                $this->error     = "Error on: {$msg}" . PHP_EOL;
				echo "msg error.\n";
                $this->connected = false;
                return false;
            }
            else
            {
                $this->msg = $msg;
				echo "msg sent.\n";
                return true;
            }
        }
        else
            return false;
    }
    function recv($bytes = 265)
    {
        if ($this->connected)
        {
            $respond = fread($this->fp, $bytes);
            if (($respond == false) || ($respond == ''))
            {
                $this->error     = "Error on receiving data" . PHP_EOL;
                $this->connected = false;
                return false;
            }
            return $respond;
        }
        else
            return false;
    }
}


$con = new ngesrukConnect('127.0.0.1', 14025);
$con->connect();

$con->send(base64_encode(serialize(array(
	'menu' => 'register',
	'param' => array(
		'function' => 'helloworld'
	),
))));

$con->send(base64_encode(serialize(array(
	'menu' => 'job',
	'param' => array(
		'function' => 'helloworld',
		'param' => 'simple text'
	),
))));

$buf = $con->recv(512);
$buf = explode(' ', $buf);
foreach($buf as $line){
	if(strlen($line) < 2) continue;
	
	$data = unserialize(base64_decode($line));
	if($data === false){
		continue;
	}else{
		print_r($data);
		$con->send(base64_encode(serialize(array(
			'menu' => 'done',
			'param' => array(
				'uniqId' => $data['uniqId'],
				'function' => $data['function'],
			),
		))));
	}
}