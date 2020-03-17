<?php

namespace App\Services\Base;

class MathService
{
    /**
     * 从string得到数字，根据mod取模
     * @param $string
     * @param $mod
     * @return int
     */
    static public function randomFromString($string,$mod)
    {
        $number = 0;
        for ($i = 0; $i < strlen($string); $i++) {
            $number = ($number * 214013 + ord($string[$i])) % $mod;
        }
        return $number;
    }

    /**
     * 取最小公约数
     * @param $num1
     * @param $num2
     * @return int|mixed
     */
    static public function getMixCommonDivisor($num1,$num2)
    {
        while (true)
        {
            if($num1 == 0 || $num2 == 0)
            {
                return max($num1,$num2);
            }
            if($num1 == $num2)
            {
                return $num1;
            }
            else if($num1 > $num2)
            {
                $num1 = $num1 % $num2;
            }
            else
            {
                $num2 = $num2 % $num1;
            }
        }
    }

    /**
     * 取大于num1且与num2互质的最小数
     * @param $num1
     * @param $num2
     * @return mixed
     */
    static public function getMixCoprimeNum($num1,$num2)
    {
        while (self::getMixCommonDivisor($num1,$num2) > 1)
        {
            $num1++;
        }
        return $num1;
    }

    /**
     * 获取一个与mod互质的数
     * @param $mod
     * @return int
     */
    static public function getRandomCoprime($mod)
    {
        while (true)
        {
            $r = rand(2,$mod - 1);
            if(self::getMixCommonDivisor($r,$mod) == 1)
            {
                return $r;
            }
        }
    }

    static public function c($m,$n)
    {
        if($n > $m)
        {
            LogService::error("math c m < n",$m,$n);
            return 0;
        }
        /** 减少计算量 */
        if($n > $m / 2)
        {
            $n = $m - $n;
        }
        $s1 = 1;
        $s2 = 1;
        if($n == 0)
            return 1;
        for($i = 0;$i < $n;$i ++)
        {
            $s1 *= $m - $i;
            $s2 *= $n - $i;
        }
        return $s1 / $s2;
    }
}
