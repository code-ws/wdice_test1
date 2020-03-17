<?php

namespace App\Models\Base;

use App\Http\Controllers\Base\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * 用于记录用户的总量
 *
 *
 * Class UserTotal
 * @package App\Models\Base
 */

class UserTotal extends Model
{
    protected $table = "users_total";

    static $cache = [];


    /**
     * 示例T_Ad
     *表示使用param1的0到11位存储数据
     * 其中0-10 一共11个bite,用户存储数据
     * 最高位第11位表示用户的记录是否超过了2^11，假如超过了，那么第11位是1，否则是0
     *
     * 当用户的值超过了2^11，例如是 0b1110111011101110,
     * 那么会分为两部分存储  0b11101 和 0b11011101110，用户有两条数据，一条的level=1,存0b11101,一条level=0,存0b11011101110
     * 数据要是再大一些，就会有level=2,level=3这样的数据
     *
     * 一个用户的多条数据通过level区分，也可以通过next_id找到，类似于链表
     */

    /*const T_Ad = [
        "name" => "ad",
        "index" => 1,
        "sub" => [0,12],
    ];*/

    /**
     * sql的写法
     * 以T_Card为例
     * 假如最高有level=3的记录，b.param1,c.param1,d.param1都需要判断是否是null
    select
    ((a.param1 >> 12) & ((1 << 2) - 1)) +
    (((IFNULL(b.param1,0) >> 12) & ((1 << 2) - 1)) << 2) +
    (((IFNULL(c.param1,0) >> 12) & ((1 << 2) - 1)) << 4) +
    (((IFNULL(d.param1,0) >> 12) & ((1 << 2) - 1)) << 6)
    from users_total as a
    left join users_total as b on a.user_id = b.user_id and a.`level` + 1 = b.`level`
    left join users_total as c on c.user_id = b.user_id and c.`level` = b.`level` + 1
    left join users_total as d on d.user_id = c.user_id and d.`level` = c.`level` + 1
    WHERE a.`level` = 0
     */

    /*const T_Card = [
        "name" => "card",
        "index" => 1,
        "sub" => [12,3]
    ];*/

    /**
     * 类似于 UserModel
     * @param $user_id
     * @param int $level
     * @return UserTotal|\Illuminate\Database\Eloquent\Builder|Model|mixed|object|null
     * @throws \Exception
     */
    static function find($user_id,$level = 0)
    {
        $className = get_class(new static());
        $cache_key = $user_id."_".$level.$className;
        if(isset(self::$cache[$cache_key]))
        {
            return self::$cache[$cache_key];
        }
        $parent = $level == 0 ? null : self::find($user_id,$level - 1);
        if($parent['next_id'] > 0)
        {
            $model = (new static())->newQuery()->where('id',$parent['next_id'])->first();
        }
        else
        {
            $model = (new static())->newQuery()->where('user_id',$user_id)->where('level',$level)->first();
        }
        /** new model */
        if($model == null)
        {
            $model = new static();
            $model['user_id'] = $user_id;
            $model['level'] = $level;
            try{
                $model->save();
            }
            catch (\Exception $exception)
            {
                Log::info($exception);
                for($i = 0;$i < 5;$i ++)
                {
                    usleep(100);
                    $model = (new static())->newQuery()->where('user_id',$user_id)->where('level',$level)->first();
                    if($model)
                    {
                        break;
                    }
                }
            }
        }
        /** 设置next_id */
        if($parent)
        {
            $parent['next_id'] = $model['id'];
            if($parent['next_id'] == $parent['id'])
            {
                throw new \Exception("next id error");
            }
            $parent->save();
        }

        self::$cache[$cache_key] = $model;
        return $model;
    }

    /**
     * type 对应的value += amount
     * @param $user_id
     * @param $type
     * @param int $amount
     * @throws \Exception
     */
    static public function typeIncrement($user_id,$type,$amount = 1)
    {
        $model = self::find($user_id);
        $model->typeInc($type,$amount);
    }

    static public function typeValue($user_id,$type)
    {
        $model = self::find($user_id);
        return $model->typeGet($type);
    }

    static public function compare($user_id,$type,$value)
    {

    }

    /**
     * 示例 type = ["sub" => [4,6]]
     * param =  0b1101100000,与这个type有关的是110110
     * 先把param又移4，得到110110，然后通过 (1 << 5) - 1得到 0b11111,然后&得到 10110  记为 l0
     * 再判断  param & (1 << (4 + 6 - 1)) 是否是1，
     * 如果不是 返回l0
     * 如果是，那么需要取上一个level的数据 记为 l1,   返回 l1 << (6 - 1) + l0
     * @param $type
     * @return int
     * @throws \Exception
     */
    public function typeGet($type)
    {
        $index = $type['index'];
        $f = (1 << ($type['sub'][1] - 1)) - 1;
        $l0 = ($this['param'.$index] >> $type['sub'][0]) & $f;
        if(($this['param'.$index] & (1 << ($type['sub'][1] + $type['sub'][0] - 1))) > 0)
        {
            $l1 =  $this->next()->typeGet($type);
            return ($l1 << ($type['sub'][1] - 1)) + $l0;
        }
        else
        {
            return $l0;
        }
    }

    /**
     * 示例 type = ["sub" => [4,6]]
     * 如果有next_id，那么需要修改上一级的内容
     * 上一级的值，通过 value & 0b1111110000 得到
     * 根据value >= (1 << 5) 判断 4 + 6 这一位是1还是0
     * set:
     * 假如 param 原先是 0b1110111011101110,先& 0b1111111111111110000001111把5-10位置为0，然后 | value
     * @param $type
     * @param $value
     * @throws \Exception
     */
    public function typeSet($type,$value)
    {
        $index = $type['index'];
        $max = 1 << ($type['sub'][1] - 1);
        $next_value = $value >> ($type['sub'][1] - 1);
        if($this['next_id'])
        {
            $this->next()->typeSet($type,$next_value);
        }
        $this['param'.$index] |= (1 << ($type['sub'][1] + $type['sub'][0] - 1));
        if($value < $max)
        {
            $this['param'.$index] -= (1 << ($type['sub'][1] + $type['sub'][0] - 1));
        }
        $value = $value & ($max - 1);
        $f = ((-$max) << $type['sub'][0]) + (1 << $type['sub'][0]) - 1;
        $this['param'.$index] = $this['param'.$index] & $f | ($value << $type['sub'][0]);
        $this->autoSave();
    }

    /**
     * type = ["sub" => [4,6]]
     * 如果 value + amount >= 1 << 5,那么下一个level也要加上 (value + amount) >> 5,并且第10位要置为1
     *  set方法等同于上面的set
     * @param $type
     * @param int $amount
     * @throws \Exception
     */
    public function typeInc($type,$amount = 1)
    {
        $index = $type['index'];
        $f = (1 << $type['sub'][1] - 1) - 1;
        $value = ($this['param'.$index] >> $type['sub'][0]) & $f;
        $value += $amount;
        $max = 1 << ($type['sub'][1] - 1);
        if($value >= $max)
        {
            $k = $value >> ($type['sub'][1] - 1);
            $this->next()->typeInc($type,$k);
            $value = $value % $max;
            $this['param'.$index] |= (1 << ($type['sub'][1] + $type['sub'][0]) - 1);
        }
        $f = ((-$max) << $type['sub'][0]) + (1 << $type['sub'][0]) - 1;
        $this['param'.$index] = $this['param'.$index] & $f | ($value << $type['sub'][0]);
        $this->autoSave();
    }

    /**
     * 获取
     * @return UserTotal|\Illuminate\Database\Eloquent\Builder|Model|mixed|object|null
     * @throws \Exception
     */
    public function next()
    {
        return self::find($this['user_id'],$this['level'] + 1);
    }

    /**
     * Controller success的时候，才保存
     */
    public function autoSave()
    {
        //测试时才打开
        //$this->save();
        Controller::registerCompleteHandler("user_total".$this['user_id']."_".$this['level'],function (){
             $this->save();
        });
    }
}
