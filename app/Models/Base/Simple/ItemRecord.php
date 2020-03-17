<?php


namespace App\Models\Base\Simple;

use App\Managers\Base\TableManager;
use App\Models\Base\SubTableModel;

class ItemRecord extends SubTableModel
{
    protected $type = TableManager::T_Item;
}
