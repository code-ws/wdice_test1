<?php

namespace App\Models\Base\Simple;

use App\Managers\Base\TableManager;
use App\Models\Base\SubTableModel;

class BalanceRecord extends SubTableModel
{
    protected $type = TableManager::T_Balance;
}
