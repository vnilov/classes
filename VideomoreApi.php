<?php
/**
 * Created by PhpStorm.
 * User: vnilov
 * Date: 07.05.15
 * Time: 14:44
 */
use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\GraphUser;
class VideomoreException extends Exception {};
class VideomoreApi
{
    private static $instance = NULL;
    //default config
    private $_app_id;
    private $_secret;

    //
    public static function getInstance($app_id = false, $secret = false) {
        if (self::$instance === NULL) {
            self::$instance = new VideomoreApi($app_id, $secret);
        }
        return self::$instance;
    }

    //
    protected function __construct($app_id = false, $secret = false) {
        if ($app_id && $secret) {
            $this->_app_id = $app_id;
            $this->_secret = $secret;
        } else {
            $this->loadDefault();
        }
    }

    //load default config
    private function loadDefault() {
        $this->_app_id = 'yyyy';
        $this->_secret = 'xxxxxxxxxxxxxxx';
    }

    //build request to videomore api	
    private function buildRequest($action = false, $params = array(), $format = 'json', $method = 'get') {
        if ($action) {
            $params['app_id'] = $this->_app_id;
            ksort($params);
            $params_str =  urldecode(http_build_query($params));
            //$params_str =  http_build_query($params);
            $sig = md5($params_str . $this->_secret);
            $url = 'https://videomore.ru/api/' . $action . '.' . $format . '?' . $params_str . '&sig=' . $sig;
            //mdump($url);
            if ($method == 'post') {
                $req = curl_init();
                curl_setopt_array($req, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query(array())
                ));
                $res = curl_exec($req);
                curl_close($req);
            } else {
                $res = file_get_contents($url);
            }
            if($action=='register_user'){
	            $loger = new CLoger('register');
	            //$loger->Add('wmregurl'.$url);
	            $loger->Add('wmreg'.$res);
            }
            if ($format != 'json') {
                return $res;
            } else {
                return json_decode($res);
            }
        } else {
            throw new VideomoreException('Missing action argument');
        }
    }
    public static function getVideo( $video_id ) {
        return self::getInstance()->buildRequest('track', array(
            'track_id' => $video_id
        ));
    }
    public static function userRegister($params){
    	$loger = new CLoger('register');
    	if(!$params["NAME"])
    		$params["NAME"]=$params["LOGIN"];
    	
    	if($params["EXTERNAL_AUTH_ID"]=="socservices"){
    		CModule::IncludeModule("socialservices");
    		if(strpos($params["PERSONAL_WWW"],"://twitter.com/")!==false){
    			$serv_name="twitter";
    			$appID = trim(self::GetOption("twitter_key"));
    			$appSecret = trim(self::GetOption("twitter_secret"));
    		}elseif (strpos($params["PERSONAL_WWW"],"://www.facebook.com/")!==false){
    			require_once(FACEBOOK_SDK_V4_SRC_AUTOLOAD);
    			$serv_name="facebook";
    			$appID = trim(CSocServFacebook::GetOption("facebook_appid"));
    			$appSecret = trim(CSocServFacebook::GetOption("facebook_appsecret"));
    			echo FACEBOOK_SDK_V4_SRC_AUTOLOAD;
    			FacebookSession::setDefaultApplication($appID, $appSecret);

    			$session = new FacebookSession($params["OATOKEN"]);
    			$session->validate();
    			$fbreq = new Facebook\FacebookRequest( $session, 'GET', '/me', Array("fields"=>"token_for_business,link,email,name"));
    			$token_for_business = $fbreq->execute()->getGraphObject(GraphUser::className())->getProperty('token_for_business');
    			
    			
    			$social_info = '{"'.$serv_name.'": {"social_app_id": "'.$appID.'", "social_id": "'.$params["XML_ID"].'","token_for_business":"'.$token_for_business.'"}}';
    			//$loger->Add('social_info'.$social_info);
    		}elseif (strpos($params["PERSONAL_WWW"],"://vk.com/")!==false){
    			$serv_name="vkontakte";
    			$appID = trim(CSocServVKontakte::GetOption("vkontakte_appid"));
    			$appSecret = trim(CSocServVKontakte::GetOption("vkontakte_appsecret"));
    		}elseif (strpos($params["PERSONAL_WWW"],"://odnoklassniki.ru/")!==false){
    			$serv_name="odnoklassniki";
    			$appID = trim(self::GetOption("odnoklassniki_appid"));
    			$appSecret = trim(self::GetOption("odnoklassniki_appsecret"));
    			$appKey = trim(self::GetOption("odnoklassniki_appkey"));
    		}
    		$params_vm = Array(
    				"authorization"=>"true",
    				"name"=>$params["NAME"],
    				"social_info"=>'{"'.$serv_name.'": {"social_app_id": "'.$appID.'", "social_id": "'.$params["XML_ID"].'"}}');
    		if($social_info)
    			$params_vm["social_info"] = $social_info;
    	}else {
    		$params_vm = Array(
    				"authorization"=>"true",
    				"name"=>$params["NAME"],
    				"email"=>$params["EMAIL"],
    				"password"=>$params["CONFIRM_PASSWORD"]
    				);
    	}
    	
    	$loger->Add('q',$params_vm);
    	return self::getInstance()->buildRequest("register_user",$params_vm , 'json','post');
    }
    public static function getProjects( $params = array() ) {
        return self::getInstance()->buildRequest('projects', $params);
    }
    public static function getCast( $project_id ) {
        return self::getInstance()->buildRequest('cast', array(
            'project_id' => $project_id
        ));
    }
    public function tv_programm( $params = array("channel"=>17) ) {
        return self::getInstance()->buildRequest('tv_programs', $params);
    }
    public static function getVideosByParams ( $params ) {
    	return self::getInstance()->buildRequest('tracks', $params);
    }
    public static function getVideos ( $project_id ) {
        return self::getInstance()->buildRequest('tracks', array(
            'project_id' => $project_id
        ));
    }
    public static function promos( $params = array() ) {
        return self::getInstance()->buildRequest('promos', $params);
    }
}
