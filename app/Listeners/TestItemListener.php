<?php

namespace App\Listeners;

use App\Listeners\Base\ItemListener;
use Illuminate\Support\Facades\Log;

class TestItemListener extends ItemListener
{
    public function collect3($user_id,$type,$amount,$method,$param1,$param2)
    {
        Log::info("collect3 $user_id,$type,$amount,$method,$param1,$param2");
    }
}
