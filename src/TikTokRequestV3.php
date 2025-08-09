<?php
namespace TikTok;

use Exception;

class TikTokRequestV3
{
    public $apiName;

    public $headerParams = array();

    public $udfParams = array();
    public $bodyData = null;

    public $fileParams = array();

    public $httpMethod = 'POST';

    public function __construct($apiName,$httpMethod = 'POST')
    {
        $this->apiName = $apiName;
        $this->httpMethod = $httpMethod;

        if($this->startWith($apiName,"//"))
        {
            throw new Exception("api name is invalid. It should be start with /");
        }
    }
    function setBodyData($body)
    {
        $this->bodyData = $body;
    }


    function addApiParam($key,$value)
    {

        if(!is_string($key))
        {
            throw new Exception("api param key should be string");
        }

        $this->udfParams[$key] = $value;
    }

    function addFileParam($key,$content,$mimeType = 'application/octet-stream')
    {
        if(!is_string($key))
        {
            throw new Exception("api file param key should be string");
        }

        $file = array(
            'type' => $mimeType,
            'content' => $content,
            'name' => $key
        );
        $this->fileParams[$key] = $file;
    }

    function addHttpHeaderParam($key,$value)
    {
        if(!is_string($key))
        {
            throw new Exception("http header param key should be string");
        }

        if(!is_string($value))
        {
            throw new Exception("http header param value should be string");
        }

        $this->headerParams[$key] = $value;
    }

    function startWith($str, $needle) {
        return strpos($str, $needle) === 0;
    }
}

?>
