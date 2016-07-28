<?php

namespace Gini\Controller\API\Admin;

class Inventory extends \Gini\Controller\API
{
    public function actionGetLimit(array $criteria = [])
    {
        $volume = null;
        $cas_no = trim($criteria['cas_no']);
        $group_id = (int) $criteria['group_id'];
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
        $infos = \Gini\ChemDB\Client::getChemicalInfo($cas_no);
        $types = [];
        if (!empty($infos)) {
            $types = $infos['types'];
        }
        $types[] = 'all';

        $db = \Gini\Database::db();
        $types = array_map(function($casNO) use ($db) {
            return $db->quote($casNO);
        }, $types);
        $types = implode(',', $types);
        $sql = "SELECT id FROM inventory_reagent WHERE cas_no IN ({$types}) AND (group_id IS NULL OR group_id={$group_id})";
        $query = $db->query($sql);
        $objs = $query->rows();
        $unit = 'g';
        $volumes = [];
        $i = \Gini\Unit\Conversion::of(['cas/'.$cas_no, 'default']);
        if ($query) foreach ($objs as $obj) {
            $reagent = a('inventory/reagent', $obj->id);
            if (!$reagent->id) continue;
            $volume = (string) $reagent->volume;
            if ($volume === '') {
                continue;
            }
            if ($reagent->group->id || !isset($volumes[$obj->cas_no])) {
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

    public function actionGetGroupLimits($groupID=null) {
        return those('inventory/reagent')
            ->whose('group_id')->is($groupID)
            ->orWhose('group_id')->is(null)
            ->get('cas_no', 'volume');
    }

    public function actionSearchGroupRequests(array $criteria=[])
    {
        $result = [
            'token'=> '',
            'count'=> 0
        ];
        $groupID = $criteria['group_id'];
        if (!$groupID) return $result;
        $group = a('group', $groupID);
        if (!$group->id) return $result;

        $result['token'] = $this->_setCriteria($criteria);
        $result['count'] = those('inventory/request')->whose('group')->is($group)->totalCount();

        return $result;
    }

    public function actionGetGroupRequests($token, $start=0, $perpage=25)
    {
        $criteria = $this->_getCriteria($token);
        $start = max($start, 0);
        $perpage = min(max(0, $perpage), 25);
        $group = a('group', $criteria['group_id']);
        if (!$group->id) return [];

		$db = \Gini\Database::db();
        $sql = "SELECT * FROM inventory_request ORDER BY status>0 ASC, ctime DESC limit {$start},{$perpage}";
        $query = $db->query($sql);
        $requests = $query ? $query->rows(\PDO::FETCH_ASSOC) : [];

        $result = [];
        if (!empty($requests)) {
            foreach ($requests as $data) {
                $request = a('inventory/request')->setData($data);
                $result[] = self::_prepareRequestData($request);
            }
        }

        return $result;
    }

    public static function actionAddRequest(array $data=[])
    {
        $cols = [];
        $cols['type'] = $type = trim($data['type']);
        $cols['cas_no'] = $casNO = trim($data['cas_no']);
        $cols['volume'] = $volume = trim($data['volume']);
        $cols['group_id'] = $groupID = (int)trim($data['group_id']);
        $cols['owner_id'] = $ownerID = (int)trim($data['owner_id']);
        $reason = trim($data['reason']);

        if (!$reason) return false;

        $user = a('user', $ownerID);
        if (!$user->id) return false;

        $group = a('group', $groupID);
        if (!$group->id) return false;

        $allowedTypes = array_keys(\Gini\ORM\Inventory\Reagent::$default_cas_nos);
        if (!in_array($type, $allowedTypes)) {
            return false;
        }

        $chem = ['default'];
        if ($casNO) {
            $chemInfo = \Gini\ChemDB\Client::getChemicalInfo($casNO);
            if (empty($chemInfo)) return false;

            $cols['name'] = $name = $chemInfo['name'];
            $types = (array)$chemInfo['types'];
            if (
                $type!=\Gini\ORM\Inventory\Reagent::CAS_DEFAULT_ALL
                &&
                empty(array_intersect($allowedTypes, $types))
            ) {
                return false;
            }

            $chem = ['cas'=> $casNO, 'state'=>$chemInfo['state'], 'default'];
        }

        if (!\Gini\Unit\Conversion::of($chem)->validate($volume)) return false;

        $cols['ctime'] = $cols['mtime'] = date('Y-m-d H:i:s');

        $request = a('inventory/request');
        $request->setData($cols);
        $request->reason = $reason;

        return !!$request->save(true);
    }

    public function actionGetHazConf(array $criteria)
    {
        $setting    = a('inventory/setting', ['key' => 'count_cart']);
        $enable      = true;
        if ($setting->id) {
            $enable = $setting->enable;
        }
        return ['count_cart'=>$enable];
    }

    private static function _prepareRequestData($request)
    {
        return [
            'type'=> $request->type,
            'cas_no'=> $request->cas_no,
            'name'=> $request->name,
            'group_id'=> $request->group->id,
            'volume'=> $request->volume,
            'status'=> $request->status,
            'ctime'=> $request->ctime,
            'owner_id'=> $request->owner->id,
            'reason'=> $request->reason,
            'reject_time'=> $request->reject_time,
            'reject_man_id'=> $request->reject_man->id,
            'approve_time'=> $request->approve_time,
            'approve_man_id'=> $request->approve_man->id,
            'mtime'=> $request->mtime,
        ];
    }

    private static $_sessionKey = 'admin-chemical-limits-api';
    private function _getCriteria($token)
    {
        $key = $this->_getSKey($token);
        return json_decode($_SESSION[$key], true);
    }

    private function _getSKey($token)
    {
        return self::$_sessionKey . '[' . $token . ']';
    }

    private function _setCriteria($criteria)
    {
        if (!is_array($criteria)) {
            $criteria = [$criteria];
        }
        $token = md5(J($criteria));
        $key = $this->_getSKey($token);
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = J($criteria);
        }
        return $token;
    }

}
