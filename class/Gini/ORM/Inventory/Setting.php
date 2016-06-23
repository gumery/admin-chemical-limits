<?php

namespace Gini\ORM\Inventory;

class Setting extends \Gini\ORM\Object
{
    // public $cas_no = 'string:120';
    // public $group  = 'object:group';
    public $key       = 'string:120';
    public $enable    = 'bigint';

    protected static $db_index = [
        'unique:key',
    ];
}
