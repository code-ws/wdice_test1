<?php

namespace App\Models\Base;

use App\Managers\Base\TableManager;
use App\Services\Base\GlobalConfigService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SubTableModel extends Model
{
    protected $table = "sub_table";

    /** @var TableManager::Type */
    protected $type;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $date = GlobalConfigService::getDate();
        $table = TableManager::tableName($this->type,$date);
        $this->setTable($table);
    }
}
