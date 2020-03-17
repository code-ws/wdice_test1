<?php

namespace App\Models\Base;

use Illuminate\Support\Arr;

class UserBind extends UserModel
{
    protected $primary = "";
    protected $auto_new = false;

    static public function primaryId($user_id)
    {
         $record = (new static())->newQuery()->where('id',$user_id)->first();
         return $record ? $record[$record->primaryKey] : null;
    }

    public function search($params)
    {
        $primary_value = Arr::get($params,$this->primary);
        return $this->newQuery()->where($this->primary,$primary_value)->first();
    }

    static public function create($user_id,$params)
    {
        $record = new static();
        $record['id'] = $user_id;
        foreach ($record->fillable as $key)
        {
            $record[$key] = Arr::get($params,$key,null);
        }
        $record->save();
        return $record;
    }
}
