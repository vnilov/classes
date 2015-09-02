<?php
require_once 'UnisenderAPI.php';

class UnisenderAPITest extends PHPUnit_Framework_TestCase {
	/**
	 * @dataProvider providerRequest
	 */
	function testRequest($method='',$args=array()){
		$obj = UnisenderAPI::getInstance();
		$res = $obj->request($method,$args);
		$this->assertObjectNotHasAttribute('error',$res,'ResultError');
	}
	function providerRequest(){
		return array (
				array (),
				array ('checkUserExists', array("email"=>"nilich@bk.ru")),
				array ('subscribe', array('fields[email]'=>"vnilov@ctcmedia.ru",'list_ids' => 4400030)),
		);
	}
}

?>