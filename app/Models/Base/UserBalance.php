<?php

namespace App\Models\Base;

class UserBalance extends UserModel
{
    protected $table = "users_balance";

    protected $fillable = [
        "money","chips"
    ];

    public function init()
    {
        $this['money'] = 0;
        $this['chips'] = 0;
        $this['bank'] = 0;
        $this['cash_merge'] = 0;
        parent::init();
    }

    public function balance()
    {
        return ["money" => $this['money'],"chips" => $this['chips']];
    }
}
