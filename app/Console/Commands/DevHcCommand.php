<?php

namespace App\Console\Commands;


use App\Managers\Base\TableManager;
use App\Models\Base\UserBalance;
use App\Models\Base\UserWorth;
use App\Services\Base\ABTestService;
use App\Services\Base\CashService;
use App\Services\Base\GlobalConfigService;
use App\Services\Base\IPLocationService;
use App\Services\Base\LoginService;
use App\Services\Base\LogService;
use App\Services\Base\PlatformConfigService;
use App\Services\Base\UserCacheService;
use App\Services\Base\RandomItemService;
use App\Services\Base\RedisService;
use App\Services\Base\ConfigService;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DevHcCommand extends Command
{
    protected $signature = "devhc {feature} {function} {param1?} {param2?} {param3?} {param4?}";

    public function handle()
    {
        $feature = $this->argument("feature");
        $function = $this->argument("function");
        $param1 = $this->hasArgument("param1") ? $this->argument("param1") : null;
        $param2 = $this->hasArgument("param2") ? $this->argument("param2") : null;
        $param3 = $this->hasArgument("param3") ? $this->argument("param3") : null;
        $param4 = $this->hasArgument("param4") ? $this->argument("param4") : null;
        Log::info($param2);
        call_user_func([$this,$feature."_".$function],$param1,$param2,$param3,$param4);
    }

    public function log($data)
    {
        dump($data);
        echo "\n";
        echo json_encode($data);
        echo "\n";
    }

    public function set_balances($user_id,$money,$chips)
    {
        $balance = ['money'=>$money,'chips'=>$chips];
        UserCacheService::setBalance($user_id,$balance);
    }
    public function get_balances($user_id)
    {
        $data = UserCacheService::balance($user_id);
        $this->log($data);
    }
    public function alloc_userid($user_id,$bundle_id,$version)
    {
        $data = ABTestService::allocUserIdTags($user_id,$bundle_id,$version);
        $this->log($data);
    }
    public function tagforname_userid($user_id,$name)
    {
        $data = ABTestService::tagForNameByUserId($user_id,$name);
        $this->log($data);
    }
    public function alloc_udid($udid,$bundle_id,$version)
    {
        $data = ABTestService::allocUdidIdTags($udid,$bundle_id,$version);
        $this->log($data);
    }
    public function get_configsforfeature($feature,$bundle_id,$version)
    {
        $data = PlatformConfigService::configsForFeature($feature,$bundle_id,$version);
        $this->log($data);
    }
    public function get_countrylevel($country)
    {
        $data = GlobalConfigService::getCountryLevel($country);
        $this->log($data);
    }
    public function get_date()
    {
        $data = GlobalConfigService::getDate();
        $this->log($data);
    }
    public function first_logintoday($user_id,$today)
    {
        $LoginService = new LoginService();
        $LoginService->recodeLoginDays($user_id,$today);
    }

    public function get_cashconfig($country,$bundle_id,$version)
    {
        $data = CashService::getCashConfig($country,$bundle_id,$version);
        $this->log($data);
    }

    public function users_worth($user_id,$worth,$is_ad)
    {
        $data = UserWorth::updateWorth($user_id,$worth,$is_ad);
        $this->log($data);
    }

    public function config_forkeys($bundle_id,$version)
    {
        $keys = ['abc','abcd','cash_config_version'];
//        $key = "abcd";
        $data = PlatformConfigService::configForKeys($keys,$bundle_id,$version);
        $this->log($data);
    }

    public function test_config()
    {
        $service = new ConfigService();
        $data = $service->config(1,"abc.xyz",42,'US');
        $this->log($data);
    }

    public function getcountry_byip($ip)
    {
        $data = IPLocationService::queryCountry($ip);
        $this->log($data);
    }

    public function send_qywxmsg()
    {
        LogService::error("test curl multi","send_qywxmsg","test");
        $this->log("over");
    }

    public function country_code($country)
    {
        $data = GlobalConfigService::getCountryCode($country);
        $this->log($data);
    }

}
