<?php

namespace App\Providers;

use App\Services\Base\RedisService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /** 验证签名 */
    public function verifySignature(Request $request)
    {
        $authorization = $request->header('Authorization');
        $params = $request->all();
        $signature = Arr::get($params,'signature','');
        if ($signature) {
            unset($params['signature']);
        }
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v)
        {
            $pairs[] = "$k=$v";
        }
        if($signature !==
            sha1($authorization . implode("&",$pairs). env('AUTH_KEY')))
        {
            $e = new \Exception("signature error",ErrorCodeService::Code_Not_Normal);
            throw $e;
        }
    }

    /**查询用户。 $authorization由用户ID + 下划线 + 随机字符串组成
     * @param $authorization
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|mixed|object|null
     */
    public function findUser($authorization)
    {
        if(is_null($authorization))
        {
            return null;
        }
        $cache_string = RedisService::center()->get($authorization);
        if(!is_null($cache_string))
        {
            $user = unserialize($cache_string);
            if($user['authorization'] != $authorization)
            {
                $user = null;
            }
        }
        else
        {
            $user = null;
            $explode_array = explode("_",$authorization);
            if(count($explode_array) >= 2)
            {
                $cache_user_id = $explode_array[0];
                if(is_numeric($cache_user_id))
                {
                    $user = User::query()->where('id', $cache_user_id)->first();
                    if($user['authorization'] != $authorization)
                    {
                        $user = null;
                    }
                }
            }
        }
        return $user;
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('api', function (Request $request) {
            $this->verifySignature($request);
            $authorization = $request->header('Authorization');
            /** @var User $user */
            $user = $this->findUser($authorization);
            if($user == null)
            {
                Log::info($request->fullUrl());
                Log::info($request->all());
                Log::info($authorization);
                Log::info(isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : $request->ip());
                throw new \Exception("not authorization",1100);
            }
            if($user['block'])
            {
                throw new \Exception("block",1099);
            }
            return $user;
        });
    }
}
