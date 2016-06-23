<?php

namespace Gini\ORM\Inventory;

class Reagent extends \Gini\ORM\Object
{
    public $cas_no = 'string:120';
    public $group  = 'object:group';
    public $volume = 'string:120';
    public $name = 'string:150';
    public $types = 'string:200';

    protected static $db_index = [
        'unique:cas_no,group',
        'name',
        'types',
    ];

    const CAS_DEFAULT_ALL   = 'all';
    const CAS_DEFAULT_HAZ   = 'hazardous';
    const CAS_DEFAULT_DRUG  = 'drug_precursor';
    const CAS_DEFAULT_TOXIC = 'highly_toxic';
    const CAS_DEFAULT_EXP   = 'explosive';

    static $default_cas_nos = [
		self::CAS_DEFAULT_ALL   => '全部',
		self::CAS_DEFAULT_HAZ   => '危化品',
		self::CAS_DEFAULT_DRUG  => '易制毒',
		self::CAS_DEFAULT_TOXIC => '剧毒品',
		self::CAS_DEFAULT_EXP   => '易制爆',
    ];
}
