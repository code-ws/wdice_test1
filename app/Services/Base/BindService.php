<?php

namespace App\Services\Base;

use App\Models\Base\UserBind;
use App\User;

class BindService
{
    public function bind($class_name,$user_id,$params)
    {
        /** @var UserBind $model */
        $model = new $class_name();
        $exist = $model->search($params);
        if($exist)
        {
            if($exist['id'] == $user_id)
            {
                return [
                    'bind' => 1,
                ];
            }
            else
            {
                $info = $exist->toArray();
                $user = User::query()->where('id',$exist['id'])->first();
                unset($info['id']);
                return [
                    'bind' => 0,
                    'user_id' => $exist['id'],
                    'info' => $info,
                    'authorization' => $user['authorization'],
                ];
            }

        }
        else
        {
            $call_params = [$class_name,"create"];
            $call_params($user_id,$params);
            return [
                'bind' => 1
            ];
        }
    }
}
