<?php

namespace App\Managers\Base;

use App\Services\Base\LogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TableManager
{
    /** 模板表 */
    const Key_Template = "template";
    /** 表名的前缀 */
    const Key_Prefix = "prefix";
    /** 仓库表 */
    const Key_Store = "store_table";
    /** 保留多少天 */
    const Key_Keep_Day = "keep_day";
    /** 表的使用时长，1天或者1个月 */
    const Key_Duration = "duration";

    /** 如果一个表是周表，那么这个表可以从周日开始算，可能从周一开始算，可能从周四开始算，需要根据具体业务需求
     * 默认0表是从周日开始算一周，1表是从周一开始算一周
     */
    const Key_Week_Offset = "week_offset";

    /** 每日的 */
    const Duration_Daily = "daily";
    /** 每月的 */
    const Duration_Monthly = "monthly";
    /** 每周的 */
    const Duration_Weekly = "weekly";

    /** 余额变化表 */
    const T_Balance = [
        self::Key_Template => "zz_balance",
        self::Key_Prefix => "z_balance_",
        self::Key_Store => "y_balance",
        self::Key_Keep_Day => 14,
        self::Key_Duration => self::Duration_Daily
    ];
    /** 广告记录表 */
    const T_Network = [
        self::Key_Template => "zz_network",
        self::Key_Prefix => "z_network_",
        self::Key_Store => "y_network",
        self::Key_Keep_Day => 14,
        self::Key_Duration => self::Duration_Daily
    ];

    /** 物品记录表 */
    const T_Item = [
        self::Key_Template => "zz_item",
        self::Key_Prefix => "z_item_",
        self::Key_Store => "y_item",
        self::Key_Keep_Day => 14,
        self::Key_Duration => self::Duration_Daily
    ];

    /** 月活跃表 */
    const T_Month_Login = [
        self::Key_Template => "zz_month_login",
        self::Key_Prefix => "z_month_login_",
        self::Key_Store => null,
        self::Key_Duration => self::Duration_Monthly,
    ];

    const T_User_Daily = [
        self::Key_Template => "zz_users_daily",
        self::Key_Prefix => "z_users_daily_",
        self::Key_Store => "y_users_daily",
        self::Key_Keep_Day => 14,
        self::Key_Duration => self::Duration_Daily
    ];

    const T_Demon_Week = [
        self::Key_Template => "zz_demon_week",
        self::Key_Prefix => "z_demon_week_",
        self::Key_Store => null,
        self::Key_Duration => self::Duration_Weekly,
        self::Key_Week_Offset => 3,//表示从周三开始算
    ];

    const Tables = [
        self::T_Balance,
        self::T_Network,
        self::T_Month_Login,
        self::T_User_Daily,
    ];

    /**创建分表
     * @param int $offset
     */
    public function createTable($offset = 0)
    {
        foreach (self::Tables as $type)
        {
            $template_name = $type[self::Key_Template];
            if (Schema::connection("mysql")->hasTable($template_name) == false) {
                LogService::error("subTable_config_error", $template_name);
                continue;
            }
            for($i = $offset - 3;$i < $offset + 4;$i ++) {
                $date = Carbon::now()->addDays($i)->format('Ymd');
                $table_name = self::tableName($type,$date);
                if (Schema::connection('mysql')->hasTable($table_name) == false) {
                    DB::connection()->statement("create table $table_name like $template_name");
                }
            }
        }
    }

    /**数据进仓库
     * @param int $offset
     */
    public function storeTable($offset = 0)
    {
        foreach (self::Tables as $type)
        {
            if(!isset($type[self::Key_Store]))
            {
                continue;
            }
            $keep_day = $type[self::Key_Keep_Day];
            for($i = $offset - $keep_day - 3;$i < $offset - $keep_day;$i ++)
            {
                $date = Carbon::now()->addDays($i)->format('Ymd');
                $table_name = self::tableName($type,$date);
                $store_name = $type[self::Key_Store];
                DB::connection()->statement("INSERT INTO $store_name SELECT * FROM $table_name");
                Schema::drop($table_name);
            }
        }
    }

    /**根据日期获得表名
     * 周表和月表会自动转换
     * @param $type
     * @param $date
     * @return string
     */
    static public function tableName($type,$date)
    {
        if($type[self::Key_Duration] == self::Duration_Daily)
        {
            return $type[self::Key_Prefix].$date;
        }
        else if($type[self::Key_Duration] == self::Duration_Monthly)
        {
            $month = floor($date / 100);
            return $type[self::Key_Prefix].$month;
        }
        else if($type[self::Key_Duration] == self::Duration_Weekly)
        {
            $offset = $type[self::Key_Week_Offset];
            $d = $date % 100;
            $m = floor($date / 100) % 100;
            $y = floor($date / 10000);
            $stamp = strtotime("$y-$m-$d 00:00:00");
            /** 1970年1月1日是周四，算出当前是星期几 */
            $left = ($stamp / 86400 + 4) % 7;
            /** 算出本周开始的时间戳 */
            $stamp -= 86400 * (($left + 7 - $offset) % 7);
            $date = Carbon::createFromTimestamp($stamp)->format('Ymd');
            return $type[self::Key_Prefix].$date;
        }
        else
        {
            echo "not find duration";
        }
    }
}
