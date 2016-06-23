<?php

namespace Gini\ORM;

class Inventory extends Object
{
    const RGT_TYPE_HAZARDOUS = 'hazardous';
    const RGT_TYPE_DRUG_PRECURSOR = 'drug_precursor';
    const RGT_TYPE_HIGHLY_TOXIC = 'highly_toxic';
    const RGT_TYPE_EXPLOSIVE= 'explosive';

    public static $rgt_types = array(
        self::RGT_TYPE_HAZARDOUS => '危险品',
        self::RGT_TYPE_DRUG_PRECURSOR => '易制毒',
        self::RGT_TYPE_HIGHLY_TOXIC => '剧毒品',
        self::RGT_TYPE_EXPLOSIVE => '易制爆',
    );

    public static $rgt_labels = array(
        self::RGT_TYPE_HAZARDOUS => 'hazar',
        self::RGT_TYPE_DRUG_PRECURSOR => 'drug',
        self::RGT_TYPE_HIGHLY_TOXIC => 'toxic',
        self::RGT_TYPE_EXPLOSIVE => 'explosive',
    );
}
