<?php

namespace App\Console\Commands;


use App\Events\Base\BalanceEvent;
use App\Events\Base\ItemEvent;
use App\Managers\Base\TableManager;
use App\Models\Base\Simple\BalanceRecord;
use App\Models\Base\UserBalance;
use App\Models\Base\UserTotal;
use App\Models\Base\UserDaily;
use App\Services\Base\ABTestService;
use App\Services\Base\ConfigService;
use App\Services\Base\GlobalConfigService;
use App\Services\Base\LogService;
use App\Services\Base\MathService;
use App\Services\Base\PushService;
use App\Services\Base\RandomItemService;
use App\Services\Base\RedisService;
use App\Services\Base\UserCacheService;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class DevClyCommand extends Command
{
    protected $signature = "devcly {feature} {function} {param1?} {param2?} {param3?} {param4?}";

    public function handle()
    {
        $feature = $this->argument("feature");
        $function = $this->argument("function");
        $param1 = $this->hasArgument("param1") ? $this->argument("param1") : null;
        $param2 = $this->hasArgument("param2") ? $this->argument("param2") : null;
        $param3 = $this->hasArgument("param3") ? $this->argument("param3") : null;
        $param4 = $this->hasArgument("param4") ? $this->argument("param4") : null;
        call_user_func([$this,$feature."_".$function],$param1,$param2,$param3,$param4);
    }

    public function log($data)
    {
        dump($data);
        echo "\n";
        echo json_encode($data);
        echo "\n";
    }

    public function abtest_names($table_choose,$bundle_id,$version)
    {
        $data = ABTestService::testNames($table_choose,$bundle_id,$version);
        $this->log($data);
    }

    public function abtest_name($name)
    {
        $data = ABTestService::configForName($name);
        $this->log($data);
    }

    public function random_items($name,$hide)
    {
        Log::info($name."$hide");
        $data = RandomItemService::itemsForName($name,$hide == 1);
        $this->log($data);
    }

    public function clear_cache()
    {
        RedisService::center()->flushall();
        RedisService::local()->flushall();
    }

    public function user_balance($user_id)
    {
        $model = UserBalance::find($user_id);
        $model['chips'] = $model['chips'] + 100;
        $model->save();
        $this->log($model->balance());
    }

    public function test_1()
    {
        $record = new BalanceRecord();
        $record['user_id'] = 1;
        $record['date'] = GlobalConfigService::getDate();
        $record['type'] = 1;
        $record['money'] = 2;
        $record['chips'] = 0;
        $record['method'] = 3;
        $record['param1'] = 0;
        $record['param2'] = 0;
        $record->save();
    }

    public function test_2()
    {
        $ts = GlobalConfigService::getDayEnd();
        dump(Carbon::createFromTimestamp($ts)->format('Y-m-d H:i:s'));
    }

    public function test_3()
    {
        $service = new ConfigService();
        $data = $service->config(2,"abc.xyz",15,'US');
        $this->log($data);
    }

    public function test_4()
    {
        ItemEvent::post(1,4,4,5,1,2);
    }

    public function create_table()
    {
        $table_manager = new TableManager();
        $table_manager->createTable();
    }

    public function test_5()
    {
        $tag1 = [
            'field' => 'tag',
            'key' => 'user_id',
            'relation' => '=',
            'value' => 1,
        ];
        PushService::pushFeatureToTags("test",[$tag1],null,null,null,3,false);
    }

    public function test_6()
    {
        echo MathService::c(50,12) / MathService::c(60,12);
    }

    public function test_7()
    {
        $user_id = 2;
        UserTotal::find($user_id)->typeInc(UserTotal::T_Card,20);
        UserTotal::find($user_id)->save();
        $value = UserTotal::typeValue($user_id,UserTotal::T_Card);
        echo "value:".$value."\n";
    }

    public function test_8()
    {
        $user_id = 2;
        UserDaily::find($user_id)->typeInc(UserTotal::T_Card,20);
        $value = UserDaily::typeValue($user_id,UserTotal::T_Card);
        echo "value1:".$value."\n";
        UserDaily::find($user_id)->typeSet(UserTotal::T_Card,2);
        $value = UserDaily::typeValue($user_id,UserTotal::T_Card);
        echo "value2:".$value."\n";
    }

    public function test_9()
    {
        $user_id = 10;
        UserDaily::typeIncrementBoth($user_id,UserDaily::Card,UserTotal::T_Ad);
        UserDaily::find($user_id)->save();
        UserTotal::find($user_id)->save();
    }
}
