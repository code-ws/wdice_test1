<?php

namespace App\Services\Base;

interface PhoneService
{
    static public function verifyStart($user_id,$phone,$type,$country_code);
    static public function verifyCheck($user_id,$phone,$code,$country_code);
}
