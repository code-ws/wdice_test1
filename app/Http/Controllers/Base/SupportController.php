<?php

namespace App\Http\Controllers\Base;

use App\Events\Base\AdEvent;
use App\Models\Base\Simple\Device;
use App\Models\Base\UserPush;
use App\Models\Base\Simple\DeviceSetting;
use App\Services\Base\LogService;
use App\Services\Base\RedisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;


class SupportController extends Controller
{
    public function connect(Request $request)
    {
        $start = microtime(true);
        $now = RedisService::center()->get('now');
        Log::info($now);
        RedisService::center()->setex('now', 86400, time());
        RedisService::local()->setex('now',86400,time());
        $redis_end = microtime(true);
        $result = DB::select("select count(*) as c from users");
        $mysql_end = microtime(true);
        $ip = isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : $request->ip();
        $data = [
            "data" => [
                'ip' => $ip,
                'times' => [
                    'start'     => $start,
                    'now'       => 0,
                    'redis_end' => $redis_end,
                    'mysql_end' => $mysql_end,
                ],

            ]
        ];
        return $this->success($data);
    }

    /**
     * 保存idfa和appsflyer_id到devices表
     * @param Request $request
     */
    public function saveIdfaAndAppsflyerId(Request $request)
    {
        $user = Auth::user();
        $user_id = $user['id'];

        $idfa = $request->post('idfa',null);
        $appsflyer_id = $request->post('appsflyer_id', null);
        $device = new Device();
        $device['user_id'] = $user_id;
        $device['idfa'] = $idfa;
        $device['appsflyer_id'] = $appsflyer_id;
        $device->save();
    }

    /**
     * 保存lat和lng到devices表
     * @param Request $request
     */
    public function saveLatAndLng(Request $request)
    {
        $user = Auth::user();
        $user_id = $user['id'];
        $lat = $request->post('lat',null);
        $lng = $request->post('lng', null);
        $device = new Device();
        $device['user_id'] = $user_id;
        $device['lat'] = $lat;
        $device['lng'] = $lng;
        $device->save();
    }

    public function saveOnesignalId(Request $request)
    {
        $user = Auth::user();
        $user_push = UserPush::find($user['id']);
        $user_push->setOnesignalId($request->input('onesignal_id'));
        $user_push->save();
    }

    /**
     * 存储客户端日志
     * @param Request $request
     * @return array
     */
    public function saveClientLog(Request $request)
    {
        $event = $request->post('event');
        $msg1 = $request->post('msg1');
        $msg2 = $request->post('msg2');
        $msg3 = $request->post('msg3');
        $msg4 = $request->post('msg4');
        $msg5 = $request->post('msg5');
        $msg6 = $request->post('msg6');
        LogService::logClient($event,$msg1,$msg2,$msg3,$msg4,$msg5,$msg6);
        return $this->success();
    }

    /**客户端存储自定义数据
     * @param Request $request
     * @return array
     */
    public function getSetting(Request $request)
    {
        $this->verifySignature($request);
        $udid = $request->get('udid');
        $record = DeviceSetting::query()->where('udid',$udid)->first();
        if($record == null)
            return $this->success();
        return $this->success($record['data']);
    }

    /**同上
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function setSetting(Request $request)
    {
        $this->verifySignature($request);
        $udid = $request->post('udid');
        $data = $request->post('data');
        $record = DeviceSetting::query()->where('udid',$udid)->first();
        if($record == null)
        {
            $record = new DeviceSetting();
            $record['udid'] = $udid;
            $record['data'] = $data;
            $record->save();
        }
        else
        {
            $record['data'] = $data;
            $record->save();
        }
        return $this->success();
    }

}
