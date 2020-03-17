<?php

namespace App\Models\Base\Simple;

use App\Managers\Base\TableManager;
use App\Models\Base\SubTableModel;
use App\Services\Base\GlobalConfigService;
use Illuminate\Database\Eloquent\Model;

class NetworkRecords extends SubTableModel
{
    protected $type = TableManager::T_Network;
}
