<?php

namespace TikTok;

use Exception;

class TikTokClient
{
	public $appkey;

	public $secretKey;

	public $gatewayUrl;

	public $connectTimeout;

	public $readTimeout;

	protected $sign = "sha256";

	protected $sdkVersion = "tiktok-sdk-php-20220621";

	public $logLevel;

	public function getAppkey()
	{
		return $this->appkey;
	}

	public function __construct($url = "",$appkey = "",$secretKey = "")
	{
		$length = strlen($url);
	    if($length == 0)
	    {    
			throw new Exception("url is empty",0);
		}
		$this->gatewayUrl = $url;
		$this->appkey = $appkey;
		$this->secretKey = $secretKey;
		$this->logLevel = Constants::$log_level_error;
	}

	protected function generateSign($apiName,$params)
	{
		unset($params["sign"]);
		unset($params["access_token"]);
		$stringToBeSigned = $this->secretKey;
		$stringToBeSigned .= $apiName;
		ksort($params);
		foreach ($params as $k => $v)
		{
			if (is_array($v)) {
				$stringToBeSigned .= $k.json_encode($v);

			}else{
				$stringToBeSigned .= "$k$v";
			}
			
		}

		$stringToBeSigned.=$this->secretKey;
		unset($k, $v);
		return $this->hmac_sha256($stringToBeSigned,$this->secretKey);
	}


	function hmac_sha256($data, $key){
	    return hash_hmac('sha256', $data, $key);
	}

	public function curl_get($url,$apiFields = null,$headerFields = null)
	{
		$ch = curl_init();

		foreach ($apiFields as $key => $value)
		{
			$url .= "&" ."$key=" . urlencode($value);
		}
		
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
	    curl_setopt($ch, CURLOPT_HEADER, false);
	    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

	    if($headerFields)
	    {
	    	$headers = array();
	    	foreach ($headerFields as $key => $value)
			{
				$headers[] = "$key: $value";
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			unset($headers);
	    }

		if ($this->readTimeout) 
		{
			curl_setopt($ch, CURLOPT_TIMEOUT, $this->readTimeout);
		}

		if ($this->connectTimeout) 
		{
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		}

		//https ignore ssl check ?
		if(strlen($url) > 5 && strtolower(substr($url,0,5)) == "https" ) 
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}

	    $output = curl_exec($ch);
		
		$errno = curl_errno($ch);

		if ($errno)
		{
			curl_close($ch);
			throw new Exception($errno,0);
		}
		else
		{
			$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if (200 !== $httpStatusCode)
			{
				throw new Exception($output,$httpStatusCode);
			}
		}

		return $output;
	}

	public function curl_post_put($url, $postFields = null, $fileFields = null,$headerFields = null, $method)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if ($this->readTimeout) 
		{
			curl_setopt($ch, CURLOPT_TIMEOUT, $this->readTimeout);
		}

		if ($this->connectTimeout) 
		{
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		}

		if($headerFields)
	    {
	    	$headers = array();
	    	foreach ($headerFields as $key => $value)
			{
				$headers[] = "$key: $value";
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			unset($headers);
	    }

		//https ignore ssl check ?
		if(strlen($url) > 5 && strtolower(substr($url,0,5)) == "https" ) 
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}

		$delimiter = '-------------' . uniqid();
		$data = '';

		if($fileFields != null)
		{
			foreach ($fileFields as $name => $file) 
			{
			    $data .= "--" . $delimiter . "\r\n";
			    $data .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $file['name'] . "\" \r\n";
			    $data .= 'Content-Type: ' . $file['type'] . "\r\n\r\n";
			    $data .= $file['content'] . "\r\n";
			}
			unset($name,$file);
		}
		$data .= "--" . $delimiter . "--";
		if ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
		}else{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}


		curl_setopt($ch, CURLOPT_HTTPHEADER , 
			array(
				'Content-Type: application/json',
			)
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
		$response = curl_exec($ch);
		unset($data);
		
		$errno = curl_errno($ch);
		if ($errno)
		{
			curl_close($ch);
			throw new Exception($errno,0);
		}
		else
		{
			$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if (200 !== $httpStatusCode)
			{
				throw new Exception($response,$httpStatusCode);
			}
		}

		return $response;
	}

	public function execute(TikTokRequest $request, $accessToken = null)
	{
		$sysParams["app_key"] = $this->appkey;
		$sysParams["sign"] = $this->sign;
		$sysParams["timestamp"] = $this->msectime();
		if (null != $accessToken)
		{
			$sysParams["access_token"] = $accessToken;
		}

		$apiParams = $request->udfParams;
		
		$requestUrl = $this->gatewayUrl;

		if($this->endWith($requestUrl,"/"))
		{
			$requestUrl = substr($requestUrl, 0, -1);
		}

		$requestUrl .= $request->apiName;
		$requestUrl .= '?';

		if($this->logLevel == Constants::$log_level_debug)
		{
			$sysParams["debug"] = 'true';
		}
		if(($request->httpMethod != 'GET') && isset($apiParams["shop_id"]))
		{
			$sysParams["shop_id"] = $apiParams['shop_id'];
		}
		$sysParams["sign"] = $this->generateSign($request->apiName,array_merge($apiParams, $sysParams));
		if($request->httpMethod != 'GET'){
			$sysParams["sign"] = $this->generateSign($request->apiName, $sysParams);
		}

	
		foreach ($sysParams as $sysParamKey => $sysParamValue)
		{
			$requestUrl .= "$sysParamKey=" . urlencode($sysParamValue) . "&";
		}

		$requestUrl = substr($requestUrl, 0, -1);
		$resp = '';
		try
		{
			if($request->httpMethod != 'GET')
			{
				$resp = $this->curl_post_put($requestUrl, $apiParams, $request->fileParams,$request->headerParams,$request->httpMethod);
			}
			else
			{
				$resp = $this->curl_get($requestUrl, $apiParams,$request->headerParams);			
			}
		}
		catch (Exception $e)
		{
			$this->logApiError($requestUrl,"HTTP_ERROR_" . $e->getCode(),$e->getMessage());
			throw $e;
		}

		unset($apiParams);

		$respObject = json_decode($resp);
		if(isset($respObject->code) && $respObject->code != "0") 
		{
			$this->logApiError($requestUrl, $respObject->code, $respObject->message);
		} else 
		{
			if($this->logLevel == Constants::$log_level_debug || $this->logLevel == Constants::$log_level_info) 
			{
				$this->logApiError($requestUrl, '', '');
			}
		}
		return $resp;
	}

	protected function logApiError($requestUrl, $errorCode, $responseTxt)
	{
//		$localIp = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : "CLI";
//		$logger = new TikTokLogger;
//		$logger->conf["log_file"] = rtrim(LAZOP_SDK_WORK_DIR, '\\/') . '/' . "logs/lazopsdk.log." . date("Y-m-d");
//		$logger->conf["separator"] = "^_^";
//		$logData = array(
//		date("Y-m-d H:i:s"),
//		$this->appkey,
//		$localIp,
//		PHP_OS,
//		$this->sdkVersion,
//		$requestUrl,
//		$errorCode,
//		str_replace("\n","",$responseTxt)
//		);
//		$logger->log($logData);
	}

	function msectime() {
	   list($msec, $sec) = explode(' ', microtime());
	   return $sec;
	}

	 function endWith($haystack, $needle) {   
	    $length = strlen($needle);
	    if($length == 0)
	    {    
	        return false;  
	    }
	    return (substr($haystack, -$length) === $needle);
	 }

    public function buildRequestParams(TikTokRequest $request, $accessToken = null)
    {
        $sysParams["app_key"] = $this->appkey;
        $sysParams["sign"] = $this->sign;
        $sysParams["timestamp"] = $this->msectime();
        if (null != $accessToken)
        {
            $sysParams["access_token"] = $accessToken;
        }

        $apiParams = $request->udfParams;

        $requestUrl = $this->gatewayUrl;

        if($this->endWith($requestUrl,"/"))
        {
            $requestUrl = substr($requestUrl, 0, -1);
        }

        $requestUrl .= $request->apiName;
        $requestUrl .= '?';

        if($this->logLevel == Constants::$log_level_debug)
        {
            $sysParams["debug"] = 'true';
        }
        if(($request->httpMethod != 'GET') && isset($apiParams["shop_id"]))
        {
            $sysParams["shop_id"] = $apiParams['shop_id'];
        }
        $sysParams["sign"] = $this->generateSign($request->apiName,array_merge($apiParams, $sysParams));
        if($request->httpMethod != 'GET'){
            $sysParams["sign"] = $this->generateSign($request->apiName, $sysParams);
        }


        foreach ($sysParams as $sysParamKey => $sysParamValue)
        {
            $requestUrl .= "$sysParamKey=" . urlencode($sysParamValue) . "&";
        }

        $requestUrl = substr($requestUrl, 0, -1);


        return [$requestUrl, array_merge($apiParams, $sysParams),$request->headerParams ];
    }
}
