<?php

namespace App\Services\Base;

use Illuminate\Support\Facades\DB;
use App\Models\Base\Simple\CashConfig;
use App\Models\Base\Simple\PlatformFeatureConfig;
use Illuminate\Support\Facades\Log;

class CashService
{
    static public function getCashConfig($country,$bundle_id,$cash_config_version)
    {
        $bundle_id_arr = "('custom','".$bundle_id."')";
        $country_level = GlobalConfigService::getCountryLevel($country);
        $data = DB::select("select * from cash_config where `amount` >= 0 and `level` = $country_level and `config_version` = $cash_config_version and `bundle_id` in $bundle_id_arr AND 
`id` not in 
    (select `id` from cash_config where `bundle_id` <> '$bundle_id' and `out_type` in 
        (select `out_type` from cash_config where `bundle_id` = '".$bundle_id."')
    ) 
ORDER BY `rank` DESC;");

        return $data;
    }


}
