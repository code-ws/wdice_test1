<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;

class UserModel extends Model
{
    static $cache = [];
    protected $auto_new = true;

    static function find($user_id)
    {
        $className = get_class(new static());
        if(isset(self::$cache[$user_id.$className]))
        {
            return self::$cache[$user_id.$className];
        }
        $model = (new static())->newQuery()->where('id',$user_id)->first();
        if(is_null($model))
        {
            $model = new static();
            if($model->auto_new == false)
                return null;
            $model['id'] = $user_id;
            $model->setIncrementing(false);
            $model->init();
            try{
                $model->save();
            }
            catch (\Exception $exception)
            {
                for($i = 0;$i < 5;$i ++)
                {
                    usleep(100);
                    $model = (new static())->newQuery()->where('id',$user_id)->first();
                    if($model)
                    {
                        break;
                    }
                }
            }
        }
        $model->active();
        self::$cache[$user_id.$className] = $model;
        return $model;
    }

    /** 初始化 */
    public function init()
    {

    }

    /** 检查并且设置 */
    public function active()
    {

    }
}
