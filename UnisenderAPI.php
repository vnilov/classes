<?php
class UnisenderException extends Exception {};

class UnisenderAPI {
	
	private static $instance = NULL;
	private $_api_key;
	private $_default_method;
	private $_lang;
	private $_ip;
	
    protected function __construct() {
		$this->_api_key = '5y1ieh7z4nwgrbkmfc5qysmyibm7shucehxec1ce';
		$this->_default_method = 'getLists';
		$this->_lang = 'ru';
		$this->_ip = $this->getIp();
	}
	
	public static function getInstance()
	{
		if(self::$instance === NULL)
		{
			self::$instance = new UnisenderAPI();
		}
		return self::$instance;
	}
	
	/*
	 * @return object
	 */
	function request($method = FALSE, $args = array()) {
		if(!$method) $method = $this->_default_method;
		$POST = array('api_key' => $this->_api_key);
		if(count($args)>0) $POST = array_merge($POST,$args);
		// Устанавливаем соединение
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $POST);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_URL, 'http://www.api.unisender.com/'.$this->_lang.'/api/'.$method.'?format=json');
		$res = json_decode(curl_exec($ch));
		return $res;
	}
	function getIp() {
	    $ipaddress = '';
	    if (getenv('HTTP_CLIENT_IP'))
	        $ipaddress = getenv('HTTP_CLIENT_IP');
	    else if(getenv('HTTP_X_FORWARDED_FOR'))
	        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
	    else if(getenv('HTTP_X_FORWARDED'))
	        $ipaddress = getenv('HTTP_X_FORWARDED');
	    else if(getenv('HTTP_FORWARDED_FOR'))
	        $ipaddress = getenv('HTTP_FORWARDED_FOR');
	    else if(getenv('HTTP_FORWARDED'))
	       $ipaddress = getenv('HTTP_FORWARDED');
	    else if(getenv('REMOTE_ADDR'))
	        $ipaddress = getenv('REMOTE_ADDR');
	    else
	        $ipaddress = 'UNKNOWN';
	    return $ipaddress;
	}
	
}

?>