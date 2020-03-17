<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base\Controller;
use App\Models\Base\Simple\RandomItemConfig;
use App\Models\MapChipsConfig;
use App\Models\MapDollarConfig;
use App\Models\MapRandomConfig;
use App\Models\MapVoidConfig;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    public function test(Request $request)
    {

    }

    public function testConfigs(Request $request)
    {
        $version = $request->input('version');
        $results = DB::select("select * from config_version where `key` = 'develop_min'");
        $user_group = $request->input('group','A');
        $develop = false;
        if($version && count($results) > 0 && $version >= $results[0]->value)
        {
            $develop = true;
        }
        $table_map_chips_config = $develop ? "test_map_chips_configs" : "map_chips_configs";
        if(!$develop && $user_group == 'B')
            $table_map_chips_config = "B_map_chips_configs";
        if(!$develop && $user_group == 'C')
            $table_map_chips_config = "C_map_chips_configs";
        if(!$develop && $user_group == 'D')
            $table_map_chips_config = "map_chips_configs";
        $records = (new MapChipsConfig)->setTable($table_map_chips_config)->newQuery()->get(["level","base","n1","n2","n3","next","roadblock","a1","b1","a2","b2","a3","b3"])->toArray();
        $chips = [];
        foreach ($records as $record)
        {
            $level = $record["level"];
            $item = $record;
            unset($item["level"]);
            $chips[$level] = $item;
        }
        $table_map_dollar_config = $develop ? "test_map_dollar_configs" : "map_dollar_configs";
        if(!$develop && $user_group == 'B')
            $table_map_dollar_config = "B_map_dollar_configs";
        if(!$develop && $user_group == 'C')
            $table_map_dollar_config = "C_map_dollar_configs";
        if(!$develop && $user_group == 'D')
            $table_map_dollar_config = "map_dollar_configs";
        $records = (new MapDollarConfig())->setTable($table_map_dollar_config)->newQuery()->get()->toArray();
        $dollar_object = [];
        foreach ($records as $record)
        {
            $money = (int)($record['user_money']);
            if(!isset($dollar_object[$money]))
            {
                $dollar_object[$money] = ["money" => $money,"k" => $record['k'],"items" => []];
            }
            $dollar_object[$money]["items"][] = ["amount" => $record['amount'],"weight" => $record['weight']];
        }
        $dollars = [];
        foreach ($dollar_object as $name => $object)
        {
            $dollars[] = $object;
        }


        $table_map_void_config = $develop ? "test_map_void_configs" : "map_void_configs";
        if(!$develop && $user_group == 'B')
            $table_map_void_config = "B_map_void_configs";
        if(!$develop && $user_group == 'C')
            $table_map_void_config = "C_map_void_configs";
        if(!$develop && $user_group == 'D')
            $table_map_void_config = "map_void_configs";
        $records = (new MapVoidConfig())->setTable($table_map_void_config)->newQuery()->get()->toArray();
        $void = [];
        foreach ($records as $record)
        {
            $level = $record['level'];
            $group = strtolower($record['group']);
            if(!isset($void[$level]))
            {
                $void[$level] = [];
            }
            if(!isset($void[$level][$group]))
            {
                $void[$level][$group] = ["amount" => $record['amount'],"items" => []];
            }
            $void[$level][$group]["items"][] = ["type" => $record['type'],"weight" => $record['weight'],
                "param" => $record['param'] ? explode(",",$record['param']) : null];
        }

        $table_map_random_config = $develop ? "test_map_random_configs" : "map_random_configs";
        if(!$develop && $user_group == 'B')
            $table_map_random_config = "B_map_random_configs";
        if(!$develop && $user_group == 'C')
            $table_map_random_config = "C_map_random_configs";
        if(!$develop && $user_group == 'D')
            $table_map_random_config = "map_random_configs";
        $records = (new MapRandomConfig())->setTable($table_map_random_config)->newQuery()->get()->toArray();
        $land = [];
        foreach ($records as $record)
        {
            $level = $record['level'];
            $name = strtolower($record['name']);
            if(!isset($land[$level]))
            {
                $land[$level] = [];
            }
            if(!isset($land[$level][$name]))
            {
                $land[$level][$name] = [];
            }
            $land[$level][$name][] = ["type" => $record['type'],"weight" => $record['weight']];
        }

        $table_random_config = $develop ? "test_random_item_configs" : "random_item_configs";
        if(!$develop && $user_group == 'B')
            $table_random_config = "B_random_item_configs";
        if(!$develop && $user_group == 'C')
            $table_random_config = "C_random_item_configs";
        if(!$develop && $user_group == 'D')
            $table_random_config = "random_item_configs";

        $config_names = [
            "slots","changemap"
        ];

        $map = ["chips" => $chips,"money" => $dollars,"void" => $void,"land" => $land];

        foreach ($config_names as $name)
        {
            $records = (new RandomItemConfig())->setTable($table_random_config)->newQuery()->where('name',$name)
                ->orderBy('level')
                ->orderBy('order')
                ->get()->toArray();
            $slots = [];
            foreach ($records as $record)
            {
                $level = $record['level'];
                if(!isset($slots[$level]))
                {
                    $slots[$level] = [];
                }
                $slots[$level][] = ["order" => $record['order'],"type" => $record['type'],"amount" => $record['amount']];
            }
            $map[$name] = $slots;
        }

        return $this->success(["map" => $map]);
    }
}
