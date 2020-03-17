<?php
/**
 * Created by PhpStorm.
 * User: cly
 * Date: 25/09/2018
 * Time: 11:12
 */

namespace App\Models\Base\Simple;
use Illuminate\Support\Facades\Log;
use App\Services\Base\HttpService;
class HttpRequest
{
    private $url;
    private $method;
    private $body;
    private $headers;
    private $params;

    private $callback;
    private $network_error_retry;

    /**
     * HttpRequest constructor.
     * @param $url
     * @param $method
     */
    public function __construct($url,$method)
    {
        $this->url = $url;
        $this->method = $method;
        $this->headers = [];
        $this->params = [];
        $this->network_error_retry = 0;
    }

    /**
     * @return mixed
     */
    public function method()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function buildUrl()
    {
        if(strcasecmp($this->method,'get') == 0 && count($this->params) > 0)
        {
            $data = [];
            foreach ($this->params as $key => $value)
            {
                $data[] = $key.'='.$value;
            }
            $appendString = implode('&',$data);
            return $this->url."?".$appendString;
        }
        else
        {
            return $this->url;
        }
    }

    /**
     * @return array
     */
    public function buildOptions()
    {
        if(strcasecmp($this->method,'post') == 0 || strcasecmp($this->method,'put') == 0)
        {
            if(count($this->params) && $this->body == null)
            {
                $this->body = json_encode($this->params);
                if(isset($this->headers["Content-Type"]) == false)
                    $this->headers["Content-Type"] = "application/json";
            }
        }
        return $this->body?["headers" => $this->headers,"body" => $this->body]:["headers" => $this->headers];
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setHeader($key,$value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function headers()
    {
        return $this->headers;
    }

    public function setHeaders($headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @param $contents
     * @return $this
     */
    public function setBody($contents)
    {
        $this->body = $contents;
        return $this;
    }

    /**
     * @return mixed
     */
    public function body()
    {
        return $this->body;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * @return mixed
     */
    public function callback()
    {
        return $this->callback;
    }

    /**
     * @param $params
     * @return $this
     */
    public function addParams($params)
    {
        $this->params = array_merge($this->params,$params);
        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function addParam($key,$value)
    {
        $this->params[$key] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function fullParams()
    {
        return $this->params;
    }

    /**
     * @param $time
     * @return $this
     */
    public function setNetworkErrorRetry($time)
    {
        $this->network_error_retry = $time;
        return $this;
    }

    /**
     * @return int
     */
    public function getNetworkErrorRetryTime()
    {
        return $this->network_error_retry;
    }

    public function retry(HttpService $service)
    {
        $service->request($this->url,$this->method)
            ->setNetworkErrorRetry($this->network_error_retry - 1)
            ->addParams($this->params)
            ->setHeaders($this->headers)
            ->setCallback($this->callback);
    }
}
