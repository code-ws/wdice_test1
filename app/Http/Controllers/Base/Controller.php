<?php

namespace App\Http\Controllers\Base;

use App\Services\Base\LogService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    //event缓存
    static protected $event_to_client = [];
    //tag缓存
    static protected $tag_to_client = [];
    //worth缓存
    static protected $worth_to_client = 0;

    static protected $complete_callbacks = [];

    /**
     * @param array $data
     * @return array
     */
    public function success($data = [])
    {
        $ret = [
            "code" => 0,
            "time" => time(),
            "message" => "success"
        ];
        //查找是否有event写入
        $client_event = self::$event_to_client;
        if (!empty($client_event)){
            $ret['event'] = self::$event_to_client;
        }
        //查找是否有tag写入
        $client_tag = self::$tag_to_client;
        if (!empty($client_tag)){
            $ret['tag'] = self::$tag_to_client;
        }
        //查找是否有worth写入
        $client_worth = self::$worth_to_client;
        if (!empty($client_worth)){
            $ret['worth'] = $client_worth;
        }
        //千分之一保存log内部逻辑未写
        $ret['data'] = $data;
        if(rand(0,1000) == 0 || env("AD_TEST",0) == 1)
        {
            try{
                $user_id = Auth::user();
            }
            catch (\Exception $e)
            {
                $user_id = 0;
            }
            $path = app('request')->path();
            LogService::log("route",$user_id,$path,json_encode(app('request')->all()),json_encode($ret));
        }
        return $ret;
    }
    //客户端打点
    /**
     * @param $event_client
     */
    static function clientEvent($event_client){
        array_push(self::$event_to_client,$event_client);
    }
    //设置tag
    /**
     * @param $tag_client_key
     * @param $tag_client_value
     */
    static function clientTag($tag_client_key,$tag_client_value){
        self::$tag_to_client[$tag_client_key] = $tag_client_value;
    }
    //消息价值
    /**
     * @param $worth_client
     */
    static function clientWorth($worth_client){
        self::$worth_to_client = $worth_client;
    }


    static function registerCompleteHandler($name,$handler)
    {
        self::$complete_callbacks[$name] = $handler;
    }

    /**对于不需要authorization的请求，需要验证签名
     * @param Request $request
     * @throws \Exception
     */
    public function verifySignature(Request $request)
    {

        $params = $request->all();
        if(isset($params['timestamp']) == false)
            throw new \Exception("signature error",1099);
        $signature = Arr::get($params, 'signature', '');
        if ($signature) {
            unset($params['signature']);
        }
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v)
        {
            $pairs[] = "$k=$v";
        }

        if($signature !==
            sha1( implode("&",$pairs). env('AUTH_KEY')))
        {
            throw new \Exception("signature error",1099);
        }
    }
}
