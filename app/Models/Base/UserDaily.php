<?php

namespace App\Models\Base;

use App\Http\Controllers\Base\Controller;
use App\Managers\Base\TableManager;
use App\Services\Base\GlobalConfigService;

class UserDaily extends UserTotal
{

    /*const Card = [
        "index" => 1,
        "sub" => [0,8],
        "name" => "card",
    ];

    const Wheel = [
        "index" => 1,
        "sub" => [8,10],
        "name" => "wheel",
    ];

    const Sign = [
        "index" => 1,
        "sub" => [18,2],
        "name" => "sign",
    ];

    const Egg = [
        "index" => 1,
        "sub" => [20,6],
        "name" => "egg"
    ];

    const Ad = [
        "index" => 1,
        "sub" => [26,6],
        "name" => "ad",
    ];*/

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $date = GlobalConfigService::getDate();
        $this['date'] = $date;
        $table = TableManager::tableName(TableManager::T_User_Daily,$date);
        $this->setTable($table);
    }

    static public function typeIncrementBoth($user_id, $type,$type_total, $amount = 1)
    {
        self::typeIncrement($user_id,$type,$amount);
        UserTotal::typeIncrement($user_id,$type_total,$amount);
    }
}
