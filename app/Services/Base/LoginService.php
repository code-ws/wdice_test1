<?php

namespace App\Services\Base;

use App\Managers\Base\TableManager;
use App\Models\Base\Simple\Device;
use App\Models\Base\UserLogin;
use App\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LoginService
{
    const Login_Exception_Admin = 1;
    const Login_Exception_Country = 2;

    /**
     * 登录入口
     * @param $params
     * @param null $user
     * @return array
     * @throws \Exception
     */
    public function login($params,$user = null)
    {
        $country = IPLocationService::queryCountry($params['ip']);
        $params['country'] = $country;

        if(is_null($user))
        {
            $device = $this->registerDevice($params);
            $user_id = $device['user_id'];
            $user = User::find($user_id);
        }
        else
        {
            $user_id = $user['id'];
        }

        $user->resetAccessToken();

        /** 审查 */
        $exception = $this->reviewInfo($user_id,$params);
        $this->saveLoginRecord($user_id,$params,$exception);
        if($exception > 0)
        {
            throw new \Exception("server error",1099);
        }
        $this->loginSuccess($user_id,$params);
        $data = $this->loginInfo($user_id);
        return $data;
    }

    /**登录成功
     * @param $user_id
     * @param $params
     */
    protected function loginSuccess($user_id,$params)
    {
        $user_login = UserLogin::find($user_id);
        $date = GlobalConfigService::getDate();
        $bundle_id = $params['bundle_id'];
        $version = $params['version'];
        $udid = $params['udid'];
        if($user_login['login_date'] != $date)
        {
            /** 每日首次登录 */
            $this->firstLoginToday($user_id,$user_login['login_date'],$date);
            $user_login['login_date'] = $date;
        }
        if($user_login['bundle_id'] != $bundle_id)
        {
            /** 首次登录这个App */
            $this->firstLoginNewApp($user_login['bundle_id'],$bundle_id);
            $user_login['bundle_id'] = $bundle_id;
        }
        if($user_login['version'] != $version)
        {
            /** 新版本首次登录 */
            $this->firstLoginNewVersion($user_login['version'],$version);
            $user_login['version'] = $version;
        }
        //udid不同
        if($user_login['udid'] != $udid)
        {
            /** 切换设备 */
            $this->changeDevice();
            $user_login['udid'] = $udid;
        }
        $this->updateCountry($user_login,$params['country']);
        /** 设置缓存 */
        UserCacheService::setBundleAndVersion($user_id,$bundle_id,$version);
        UserCacheService::setCountry($user_id,$user_login['country']);
        $user_login->save();

        /** 设置onesignal标签 */
        PushService::setTagForUser($user_id);
    }

    /**审查用户信息，例如可能判断国家
     * @param $user_id
     * @param $params
     * @return int
     */
    protected function reviewInfo($user_id,$params)
    {
        $user = User::find($user_id);
        /** 被管理员封了 */
        if($user['block'])
        {
            return self::Login_Exception_Admin;
        }
        return 0;
    }

    /**查找设备
     * @param $params
     * @return Device
     */
    protected function registerDevice($params)
    {
        $udid = $params['udid'];
        /** @var Device $device */
        $device = Device::query()->where('udid',$udid)->first();
        if(is_null($device))
        {
            $user = $this->newUser(Arr::get($params,'bundle_id'),Arr::get($params,'version'));
            $device = new Device();
            $device['udid'] = $udid;
            $device['user_id'] = $user['id'];
            $device['bundle_id'] = Arr::get($params,'bundle_id');
            $device['version'] = Arr::get($params,'version');
            $device['country'] = Arr::get($params,'country');
            $this->updateDeviceInfo($device,$params);
        }
        else
        {
            $this->updateDeviceInfo($device,$params);
        }
        return $device;
    }

    /**
     * 生成新的账号
     * @param $bundle_id
     * @param $version
     * @return User
     */
    protected function newUser($bundle_id,$version)
    {
        $user = new User();
        $user->resetAccessToken();
        BankService::newUser($user['id'],$bundle_id,$version);
        return $user;
    }

    /**更新国家，涉及到权重
     * @param UserLogin $login
     * @param $country
     */
    protected function updateCountry(UserLogin $login, $country)
    {
        /** 初始化，权重10 */
        if(!isset($login['country']))
        {
            $login['country'] = $country;
            $login['country_weight'] = 10;
        }
        /** 国家相同，权重+3 */
        else if($login['country'] == $country)
        {
            $login['country_weight'] = $login['country_weight'] + 3;
        }
        else
        {
            /** 国家不同，权重-1 */
            $login['country_weight'] = $login['country_weight'] - 1;
            /** 权重为0，切换国家 */
            if($login['country_weight'] <= 0)
            {
                $login['country'] = $country;
                $login['country_weight'] = 1;
            }
        }
    }

    /**这里不包含idfa和appsflyer这样的三方ID
     * @param Device $device
     * @param $params
     */
    protected function updateDeviceInfo(Device $device, $params)
    {
        $device['ip'] = $params['ip'];
        $device['language'] = Arr::get($params,'language',null);
        $device['timezone'] = Arr::get($params,'timezone',null);
        /** 这三项初始化以后就不再改变 */
        //$this->updateCountry($device,$country);
        //$device['bundle_id'] = Arr::get($params,'bundle_id',null);
        //$device['version'] = Arr::get($params,'version',null);
        $device['device_info'] = json_encode($params);
        $device->save();
    }

    protected function saveLoginRecord($user_id,$params,$exception)
    {
        $udid = $params['udid'];
        $bundle_id = $params['bundle_id'];
        $ip = $params['ip'];
        $country = $params['country'];
        $language = Arr::get($params,'language',null);
        $timezone = Arr::get($params,'timezone',null);
        $version = $params['version'];
        $device_info = json_encode($params);
        /** 这里设置onesignal的语言分组 */
        PushService::setLanguageGroup($user_id,$language);
        DB::insert("insert into login_records (`udid`,`user_id`,`bundle_id`,`ip`,`country`,`language`,`timezone`,`version`,`device_info`,`exception`) values ($udid,$user_id,$bundle_id,$ip,$country,$language,$timezone,$version,$device_info,$exception)");
    }

    public function firstLoginToday($user_id,$last_date,$today)
    {
        $this->recodeLoginDays($user_id,$today);
    }

    public function firstLoginNewVersion($last_version,$new_version)
    {

    }

    public function firstLoginNewApp($last_bundle_id,$new_bundle_id)
    {

    }

    /**最后把这个信息返回给客户端
     * @param $user_id
     * @return array
     */
    public function loginInfo($user_id)
    {
        $user = User::find($user_id);
        return [
            'authorization' => $user['authorization'],
            'balance' => UserCacheService::balance($user_id),
            'date' => GlobalConfigService::getDate(),
        ];
    }

    /**
     * 记录登录天数
     * @param $user_id
     * @param $today
     */
    public function recodeLoginDays($user_id,$today)
    {
        $day = $today % 100;
        $this_day = 1 << $day;
        $table_name = TableManager::tableName(TableManager::T_Month_Login,$today);
        DB::insert ("insert into $table_name (`user_id`,`days`) values ($user_id,$this_day) ON DUPLICATE KEY update days = days | $this_day");
    }
    /**
     * 切换设备
     */
    public function changeDevice()
    {

    }
}
