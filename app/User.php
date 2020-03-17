<?php

namespace App;


use App\Models\Base\UserModel;
use App\Services\Base\RedisService;
use Illuminate\Auth\Authenticatable;
use Illuminate\Support\Str;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends UserModel implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [

    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [

    ];

    public function resetAccessToken()
    {
        RedisService::center()->del($this['authorization']);
        $this['authorization'] = $this['id']."_".Str::random(100);
        RedisService::center()->setex($this['authorization'],600,serialize($this));
        $this->save();
    }
}
