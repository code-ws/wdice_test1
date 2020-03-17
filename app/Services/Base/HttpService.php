<?php
/**
 * Created by PhpStorm.
 * User: cly
 * Date: 25/09/2018
 * Time: 11:09
 */

namespace App\Services\Base;

use App\Supports\MapIterator;
use App\Supports\ExpectingIterator;
use App\Models\Base\Simple\HttpRequest;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class HttpService
{
    private $requests;
    private $iterator;
    private $ret;
    private $exceptions;
    private $time_out;
    private $cookie;


    public $concurrency = 200;

    public function __construct()
    {
        $this->requests = [];
        $this->ret = [];
        $this->exceptions = [];
        $this->time_out = env('HTTP_TIMEOUT');
    }

    /**
     * @param $url
     * @param $method
     * @return HttpRequest
     */
    public function request($url,$method)
    {
        $request = new HttpRequest($url,$method);
        if($this->iterator)
        {
            $this->iterator->append($request);
        }
        else
        {
            $this->requests[] = $request;
        }
        return $request;
    }

    /**
     * @param HttpRequest $request
     * @return array
     */
    public function buildOptions(HttpRequest & $request)
    {
        return $request->buildOptions();
    }

    /**
     * @param HttpRequest $request
     * @return string
     */
    public function buildUrl(HttpRequest & $request)
    {
        return $request->buildUrl();
    }

    public function retData($index = 0)
    {
        if(count($this->ret) > $index)
            return $this->ret[$index];
        return null;
    }

    public function setCookie($cookie)
    {
        $this->cookie = $cookie;
    }

    public function waiting()
    {
        if($this->cookie)
        {
            $client = new Client([ 'timeout' => $this->time_out,"cookies" => $this->cookie]);
        }
        else
        {
            $client = new Client([ 'timeout' => $this->time_out]);
        }


        $this->iterator = new \ArrayIterator($this->requests);
        $generator = new MapIterator(
            $this->iterator,
            function (HttpRequest $request, $array) use ($client) {
                return $client->requestAsync($request->method(), $this->buildUrl($request),$this->buildOptions($request))
                    ->then(function (Response $response) use ($request, $array) {
                        if($request->callback())
                        {
                            try
                            {
                                $ret = call_user_func($request->callback(),$response->getBody()->getContents(),$response->getStatusCode());
                                if($ret instanceof \Exception)
                                {
                                    //Log::info("save exception");
                                    $this->exceptions[] = $ret;
                                }
                                else if(is_array($ret) && isset($ret['key']) && isset($ret['value']))
                                {
                                    $this->ret[$ret['key']] = $ret['value'];
                                    //Log::info($this->ret);
                                }
                                else if($ret)
                                {
                                    $this->ret[] = $ret;
                                    //Log::info($this->ret);
                                }

                            }
                            catch (\Exception $e)
                            {
                                Log::info($request->buildUrl());
                                Log::info($e);
                                echo $e->getMessage();
                                echo "\n";
                            }

                        }
                    },function (RequestException $exception) use ($request,$array){
                        if($exception->getCode() == 0 && $request->getNetworkErrorRetryTime() > 0)
                        {
                            Log::info("retry ".$request->buildUrl());
                            $request->retry($this);
                        }
                        else if($request->callback())
                        {
                            try
                            {
                                $message = $exception->getResponse() ? $message = $exception->getResponse()->getBody()->getContents() : $exception->getMessage();
                                Log::info($request->buildUrl());
                                Log::info($request->buildOptions());
                                Log::info($message);
                                $code = $exception->getCode();
                                $ret = call_user_func($request->callback(),$message,$code);
                                if($ret instanceof \Exception)
                                {
                                    Log::info("save exception");
                                    $this->exceptions[] = $ret;
                                }
                                else if(is_array($ret) && isset($ret['key']) && isset($ret['value']))
                                {
                                    $this->ret[$ret['key']] = $ret['value'];
                                }
                                else
                                {
                                    $this->ret[] = $ret;
                                }
                            }
                            catch (\Exception $e)
                            {
                                Log::info($request->buildUrl());
                                Log::info($e);
                            }

                        }
                        else
                        {
                            echo "no callback";
                        }
                    });
            }
        );
        $generator = new ExpectingIterator($generator);
        $promise = \GuzzleHttp\Promise\each_limit($generator, $this->concurrency);
        $promise->wait();
        $this->requests = [];
        $this->iterator = null;
        if(count($this->exceptions))
            throw $this->exceptions[0];
    }

    public function setTimeOut($time)
    {
        $this->time_out = $time;
    }
}
