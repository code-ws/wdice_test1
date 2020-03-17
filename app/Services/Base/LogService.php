<?php

namespace App\Services\Base;

use App\Models\Base\Simple\LogModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LogService
{
    const hc_robot_url = "https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=4912927b-b873-4a8c-bc4b-7a25d8bdd8eb";


    static protected function save(LogModel $record,$event,$msg1,$msg2 = null,$msg3 = null,$msg4 = null, $msg5 = null,$msg6 = null)
    {
        $record['event'] = $event;
        $record['msg1'] = $msg1;
        $record['msg2'] = $msg2;
        $record['msg3'] = $msg3;
        $record['msg4'] = $msg4;
        $record['msg5'] = $msg5;
        $record['msg6'] = $msg6;
        $record->save();
    }

    static public function logClient($event,$msg1 = null,$msg2 = null,$msg3 = null,$msg4 = null, $msg5 = null,$msg6 = null)
    {
        $record = new LogModel();
        $record->setTable("log_client");
        self::save($record,$event,$msg1,$msg2,$msg3,$msg4,$msg5,$msg6);
    }

    static public function log($event,$msg1,$msg2 = null,$msg3 = null,$msg4 = null, $msg5 = null,$msg6 = null)
    {
        $record = new LogModel();
        self::save($record,$event,$msg1,$msg2,$msg3,$msg4,$msg5,$msg6);
    }

    static public function alert($event,$msg1,$msg2 = null,$msg3 = null,$msg4 = null, $msg5 = null,$msg6 = null)
    {
        $record = new LogModel();
        $record->setTable("log_important");
        self::save($record,$event,$msg1,$msg2,$msg3,$msg4,$msg5,$msg6);
    }

    static public function error($event,$msg1,$msg2 = null,$msg3 = null,$msg4 = null, $msg5 = null,$msg6 = null)
    {
        $record = new LogModel();
        $record->setTable("log_important");
        self::save($record,$event,$msg1,$msg2,$msg3,$msg4,$msg5,$msg6);
        self::sendMsgToQywx($event,$msg1,$msg2,$msg3,$msg4,$msg5,$msg6,true);
    }

    /**
     * 使用企业微信机器人发消息
     * @param $event
     * @param $msg1
     */
    static public function sendMsgToQywx($event,$msg1,$msg2,$msg3,$msg4,$msg5,$msg6,$is_error)
    {
        $url = self::hc_robot_url;
        $post_data = self::getPostData($event,$msg1,$msg2,$msg3,$msg4,$msg5,$msg6,$is_error);
        //通过手机号@成员
//        $post_data['text']['mentioned_mobile_list'] = ['15680585730','18408249467'];
        self::curl_json_post($url,$post_data);

    }

    static public function getPostData($event,$msg1,$msg2,$msg3,$msg4,$msg5,$msg6,$is_error)
    {
        $post_data['msgtype'] = "text";
        $post_data['text']['content'] = $event;
        $arr = [$msg1,$msg2,$msg3,$msg4,$msg5,$msg6];
        foreach ($arr as $value)
        {
            if ($value != "" && $value != null)
            {
                $post_data['text']['content'] .= ",".$value;
            }
        }
        //如果是error报错,@所有人
        if ($is_error)
        {
            $post_data['text']['mentioned_list'] = ['@all'];
        }
        return $post_data;
    }
    static public function curl_json_post($url, $data = NULL)
    {

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if(!$data){
            return 'data is null';
        }
        if(is_array($data))
        {
            $data = json_encode($data);
        }
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER,array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length:' . strlen($data),
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        $errorno = curl_errno($curl);
        if ($errorno) {
            return $errorno;
        }
        curl_close($curl);
        return $res;

    }
}
