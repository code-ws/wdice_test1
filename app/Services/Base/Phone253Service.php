<?php
/**
 * Created by PhpStorm.
 * User: cly
 * Date: 14/05/2019
 * Time: 02:05
 */

namespace App\Services\Base;

use App\Jobs\Job;
use App\Services\Base\PlatformConfigService;
use App\Services\Base\RedisService;
use App\Services\Base\UserCacheService;
use Illuminate\Support\Facades\Log;

/**
 * 253创蓝的短信服务
 * Class Phone253Service
 * @package App\Services\Base
 */
class Phone253Service implements PhoneService
{
    const smsAccount = "I7347703";
    const smsPWD = "QIXsMtHVeEc011";
    const callAccount = "I4513206";
    const callPWD = "Q5WTIgrNLv885d";

    static public function sendSMS($phone,$text)
    {
        $service = new HttpService();
        $service->request("http://intapi.253.com/send/json","post")
            ->addParam("account",self::smsAccount)
            ->addParam("password",self::smsPWD)
            ->addParam("msg",$text)
            ->addParam("mobile",$phone)
            ->setCallback(function ($content,$code){
                if($code == 200)
                {
                    $data = json_decode($content,true);
                    if($data['code'] == 0)
                    {
                        return ["result" => true,"message_id" =>  $data['msgid']];
                    }
                    else
                    {
                        return ["result" => false,"error" => $content];
                    }
                }
                return ["result" => false,"error" => $content];
            });
        $service->waiting();
        return $service->retData();
    }

    static public function sendCall($phone,$intro,$code,$outro = "")
    {
        $service = new HttpService();
        $service->request("http://intapi.253.com/sendVoice/OTP",'post')
            ->addParam("account",self::callAccount)
            ->addParam("password",self::callPWD)
            ->addParam("intro",$intro)
            ->addParam("code",$code)
            ->addParam("mobile",$phone)
            ->addParam("outro",$outro)
            ->setCallback(function ($content,$code){
                if($code == 200)
                {
                    $data = json_decode($content,true);
                    if($data['code'] == 0)
                    {
                        return ["result" => true,"message_id" =>  $data['msgid']];
                    }
                    else
                    {
                        return ["result" => false,"error" => $content];
                    }
                }
                return ["result" => false,"error" => $content];
            });
        $service->waiting();
        return $service->retData();
    }

    static public function verifyStart($user_id,$phone,$type,$country_code)
    {
        $bc = UserCacheService::bundleAndVersion($user_id);
        $app_name = PlatformConfigService::configForKey("app_name",$bc['bundle_id'],$bc['version']);
        $intro = "Your ".$app_name." verification code is ";
        $key = "verification_code_".$phone.$user_id;
        $outro = '. Please enter within 10 mins before it expires.';
        if(RedisService::center()->exists($key))
        {
            $code = RedisService::center()->get($key);
            RedisService::center()->setex($key,600,$code);
        }
        else
        {
            $code = rand(100000,999999);
            RedisService::center()->setex($key,600,$code);
        }

        if($type == 'sms')
        {
            $result = self::sendSMS($phone,$intro.$code.$outro);
        }
        else
        {
            $result = self::sendCall($phone,$intro,$code,$outro);
        }
        if(is_numeric($user_id))
        {
            if($result['result'])
            {
                LogService::log($user_id,"verify start 253 success",$result['message_id'],$phone,$code,$type);
            }
            else
            {
                LogService::log($user_id,"verify start 253 failed",$result['error'],$phone,$code,$type);
            }
        }

        return $result['result'];
    }

    static public function verifyCheck($user_id,$phone,$code,$country_code)
    {
        $key = "verification_code_".$phone.$user_id;
        $stock = RedisService::center()->get($key);
        if($stock == $code)
        {
            RedisService::center()->del($key);
            if(is_numeric($user_id))
                LogService::log($user_id,"verify success 253",$phone,$code,1);
            return true;
        }
        else
        {
            if(is_numeric($user_id))
                LogService::log($user_id,"verify fail 253",$phone,$code,$stock);
            return false;
        }
    }
}
