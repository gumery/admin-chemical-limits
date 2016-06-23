<?php

namespace Gini\Controller\API\Admin;

class Inventory extends \Gini\Controller\API
{
    public function actionGetLimit(array $criteria = [])
    {
        $volume = null;
        $cas_no = trim($criteria['cas_no']);
        $group_id = (int) $criteria['group'];
        if (!$cas_no) {
            return $volume;
        }
        $group = a('group', $group_id);

        $reagent = a('inventory/reagent', ['cas_no' => $cas_no, 'group' => $group]);
        if ($reagent->id && $reagent->volume !== '') {
            return $reagent->volume;
        }

        $reagent = a('inventory/reagent', ['cas_no' => $cas_no]);
        if ($reagent->id && $reagent->volume !== '') {
            return $reagent->volume;
        }

        $infos = \Gini\ChemDB\Client::getProduct($cas_no);
        $types = [];
        if (!empty($infos)) {
            $types = array_keys($infos);
        }
        $types[] = 'all';

        $db = \Gini\Database::db();
        $types = array_map(function($casNO) use ($db) {
            return $db->quote($casNO);
        }, $types);
        $types = implode(',', $types);
        $sql = "SELECT id FROM inventory_reagent WHERE cas_no IN ({$types}) AND (group_id IS NULL OR group_id={$group_id})";
        $query = $db->query($sql);
        $unit = 'g';
        $volumes = [];
        $i = \Gini\Unit\Conversion::of(['cas/'.$cas_no, 'default']);
        if ($query) foreach ($query->rows() as $obj) {
            $reagent = a('inventory/reagent', $obj->id);
            if (!$reagent->id) continue;
            $volume = (string) $reagent->volume;
            if ($volume === '') {
                continue;
            }
            if ($obj->group->id || !isset($volumes[$obj->cas_no])) {
                $volumes[$obj->cas_no] = $i->from($volume)->to($unit);
            }
        }
        sort($volumes);
        $volume = (string) current($volumes);
        return $volume==='' ? '' : $volume.$unit;
    }

    public function actionGetSettings(array $criteria = [])
    {
        $setting    = a('inventory/setting', ['key' => 'count_cart']);
        $enable      = true;
        if ($setting->id) {
            $enable = $setting->enable;
        }
        return [
            'count_cart' => $enable
        ];
    }

    public function actionGetGroupLimits($group_id=null) {
        return those('inventory/reagent')
            ->whose('group_id')->is($group_id)
            ->orWhose('group_id')->is(null)
            ->get('cas_no', 'volume');
    }
}
