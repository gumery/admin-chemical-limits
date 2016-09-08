<?php

namespace Gini\Controller\CGI\AJAX\Inventory;

class Reagent extends \Gini\Controller\CGI
{
    public function actionMore($start = 0)
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;

        $params = [];
        $perpage = 15;
        $start = max(0, $start);
        $form = $this->form('post');

        $db = \Gini\Database::db();
        $casNOs = implode(',', array_map(function($casNO) use ($db) {
            return $db->quote($casNO);
        }, array_keys(\Gini\ORM\Inventory\Reagent::$default_cas_nos)));
        $sql = "SELECT * FROM inventory_reagent WHERE group_id IS NULL AND cas_no NOT IN ({$casNOs})";
        $params = [];
        if ($type = trim($form['type'])) {
            $sql = "{$sql} AND types LIKE :type";
            $params[':type'] = "%{$type}%";
        }
        if ($keyword = trim($form['q'])) {
            $sql = "{$sql} AND (name LIKE :name OR cas_no LIKE :cas_no)";
            $params[':name'] = "%{$keyword}%";
            $params[':cas_no'] = "%{$keyword}%";
        }

        $sql = "{$sql} ORDER BY id DESC LIMIT {$start},{$perpage}";
        $query = $db->query($sql, null, $params);

        $reagents = [];
        $casNOs = [];
        if ($query) foreach ($query->rows() as $obj) {
            $reagents[] = self::_convertToReagent($obj);
            $casNOs[] = $obj->cas_no;
        }

        $subReagents = self::_fetchReagents($casNOs);

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/reagents-search-results', [
            'start'=>$start,
			'reagents'=> $reagents,
            'subReagents'=> $subReagents,
            'more'=> (count($reagents) != $perpage) ? -1 : ($start + $perpage),
        ]));

    }

    public function actionGetSubs()
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;

        $form = $this->form();
        $casNO = trim($form['cas_no']);
        $type  = trim($form['type']);
        if (!$casNO) return;

        $subReagents = self::_fetchReagents([$casNO]);
        $subReagents = $subReagents[$casNO] ?: [];

        if ($type == 'type') {
            $view = 'inventory/reagents-search-results-type-sub';
        }elseif ($type == 'cas') {
            $view = 'inventory/reagents-search-results-sub';
        }
        // $view = 'inventory/reagents-search-results-sub';
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V($view, [
            'subs'=> $subReagents
        ]));
    }

    private static function _fetchReagents($casNOs)
    {
        if (empty($casNOs)) return [];
        $db = \Gini\Database::db();
        $casNOs = implode(',', array_map(function($casNO) use ($db) {
            return $db->quote($casNO);
        }, $casNOs));
        $sql = "SELECT * FROM inventory_reagent WHERE cas_no IN ({$casNOs}) AND group_id IS NOT NULL ORDER BY cas_no";
        $query = $db->query($sql);
        $result = [];
        if ($query) foreach ($query->rows() as $obj) {
            $result[$obj->cas_no][] = self::_convertToReagent($obj);
        }
        return $result;
    }

    private static function _convertToReagent($object)
    {
        return a('inventory/reagent')->setData([
            'id'=> $object->id,
            'cas_no'=> $object->cas_no,
            'name'=> $object->name,
            'type'=> explode(',', $object->types),
            'volume'=> $object->volume,
            'group_id'=> $object->group_id,
            '_extra'=> $object->_extra
        ]);
    }

    private static function _checkVal($value,$cas)
    {
        $default_cas_nos  = \Gini\ORM\Inventory\Reagent::$default_cas_nos;
        $value = (string)$value;
        // 设置为空，表示不限制购买
        if ($value==='') return true;
        $criteria = [];
        $has_types = array_keys(\Gini\ORM\Inventory::$rgt_types);
        if (!array_key_exists($cas, $default_cas_nos)) {
            $chem = (array)\Gini\ChemDB\Client::getChemicalInfo($cas);
            foreach ($chem as $type => $chem) {
                //根据优先级（易制毒 > 易制爆 > 剧毒品 > 危化品 ）选择类别
                if (in_array($type, $haz_types)) {
                    $state = $chem['state'];
                    break;
                }
            }
            if ($state) {
                $criteria = ['cas' => $cas, 'state' => $state, 'default'];
            }
        }

        if (!$criteria) {
            $chem = ['default'];
        }

        return \Gini\Unit\Conversion::of($chem)->validate($value);

    }

    public function actionSetConf()
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;

        $form    = $this->form('post');
        $enable  = (int)$form['enable'];
        $setting = a('inventory/setting', ['key'=>'count_cart']);
        if (!$setting->id) {
            $setting->key = 'count_cart';
        }
        $setting->enable = $enable;
        $setting->save();
    }

    public function actionDeleteVolume()
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                'code'=> 1,
                'message'=> T('请您重新登录')
            ]);
        }

        $form = $this->form('post');
        $casNO = trim($form['cas']);
        $groupID = trim($form['group']);
        if (!$casNO) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                'code'=> 2,
                'message'=> T('删除失败, 请重试')
            ]);
        }

        if ($groupID) {
            $group = a('group', (int)$groupID);
            if (!$group->id) {
                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                    'code'=> 3,
                    'message'=> T('删除失败, 请重试')
                ]);
            }
            $reagent = a('inventory/reagent', [
                'cas_no'=> $casNO,
                'group'=> $group
            ]);

            if (!$reagent->id) {
                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                    'code'=> 4,
                    'message'=> T('删除失败, 请重试')
                ]);
            }

            $bool = $reagent->delete();
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                'code'=> $bool ? 0 : 5,
                'message'=> $bool ? T('删除成功') : T('删除失败, 请重试')
            ]);
        }

        $db = \Gini\Database::db();
        $sql = "DELETE FROM inventory_reagent WHERE cas_no=:cas_no";
        $bool = $db->query($sql, null, [
            ':cas_no'=> $casNO
        ]);

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'code'=> $bool ? 0 : 6,
            'message'=> $bool ? T('删除成功') : T('删除失败, 请重试')
        ]);
    }

    public function actionEditVolume()
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                'code'=> 1,
                'message'=> T('请您重新登录')
            ]);
        }

        $form = $this->form('post');
        $volume = trim($form['volume']);
        $groupID = trim($form['group']);
        $cas = trim($form['cas']);

        if (!$cas) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                'code'=> 6,
                'message'=> T('设置失败，请您重试')
            ]);
        }

        if (!self::_checkVal($volume, $cas)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                'code'=> 2,
                'message'=> T('上限设置格式错误，请输入数字＋单位，例5mg（目前系统支持单位: 瓶/bottle/ml/g/cm3/ul/μl/ml/cl/dl/l/gal/lb/ug/μg/mg/kg/oz/lb/）')
            ]);

        }

        if ($cas && !in_array($cas, array_keys(\Gini\ORM\Inventory\Reagent::$default_cas_nos))) {
            $chemicalInfo = \Gini\ChemDB\Client::getChemicalInfo($cas);
            if (empty($chemicalInfo) ||
                !count(array_intersect($chemicalInfo['types'], array_keys(\Gini\ORM\Inventory::$rgt_types)))
                ) {
                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                    'code'=> 4,
                    'message'=> T('设置失败，请您重试')
                ]);
            }
        }

        if ($groupID) {
            $group = a('group', (int)$groupID);
            if (!$group->id) {
                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                    'code'=> 5,
                    'message'=> T('设置失败，请您重试')
                ]);
            }
            $reagent = a('inventory/reagent', ['cas_no'=>$cas, 'group'=> $group]);
        } else {
            $reagent = those('inventory/reagent')->whose('cas_no')->is($cas)->andWhose('group_id')->is(null)->current();
        }

        if (!$reagent->id) {
            $reagent = a('inventory/reagent');
            $reagent->cas_no = $cas;
            if ($groupID) {
                $reagent->group = $group;
            }
        }
        if (!empty($chemicalInfo)) {
            $reagent->name = $chemicalInfo['name'];
            $reagent->types = implode(',', $chemicalInfo['types']);
            $reagent->state = $chemicalInfo['state'];
        }
        $reagent->volume = $volume;
        $bool = $reagent->save();

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'code'=> $bool ? 0 : 3,
            'message'=> $bool ? T('修改成功') : T('修改失败, 请重试'),
        ]);
    }

    public function actionGetEditGroupVolume() {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;

        $form = $this->form('get');
        if (empty($form)) return;
        $type = $form['type'];
        $cas_no = $form['cas_no'];
        $tid = $form['tid'];
        /*
            获取所有的课题组名称
         */


        return \Gini\Ioc::construct('\Gini\CGI\Response\HTML', V('inventory/edit-group-volume',[
                'type' => $type,
                'cas_no' => $cas_no,
                'tid'=> $tid
            ]));
    }

    public function actionGetReagentAppendChemicalModal()
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;

        return \Gini\Ioc::construct('\Gini\CGI\Response\HTML', V('inventory/reagent-append-chemical-modal'));
    }

    public function actionSearchGroup()
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;

        $form = $this->form('post');
        if (empty($form) || !($q = trim($form['q']))) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', []);
        }

        $criteria = [
            'keyword'=> $q
        ];

        $result = self::getGateWayRPC()->gateway->organization->getLabs($criteria);
        if (empty($result)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', []);
        }

        $data = [];
        foreach ($result as $d) {
            $data[] = [
                'key'=> $d['code'],
                'value'=> $d['name']
            ];
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);

    }

    public function actionSearchChemical()
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;

        $form = $this->form();
        if ($keyword = trim($form['q'])) {
            $params['keyword'] = $keyword;
        }

        $data = [];
        try {
            $rpc = \Gini\ChemDB\Client::getRPC();
            $result = $rpc->chemdb->searchChemicals($params);
            $chems = (array)$rpc->chemdb->getChemicals($result['token']);
            foreach ($chems as $chem) {
                $data[] = [
                    'key'=> $chem['cas_no'],
                    'value'=> $chem['name']
                ];
            }
        }
        catch (\Exception $e) {
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
    }

    private static $gateway_rpc;
    private function getGateWayRPC()
    {
        if (self::$gateway_rpc) return self::$gateway_rpc;
        $confs = \Gini\Config::get('app.rpc');

        $gateway = (array) $confs["gateway"];
        $gatewayURL = $gateway['url'];
        $clientID = $gateway['client_id'];
        $clientSecret = $gateway['client_secret'];

        $rpc = \Gini\IoC::construct('\Gini\RPC',$gatewayURL);
        if (!$rpc->gateway->authorize($clientID, $clientSecret)) {
            return;
        }
        self::$gateway_rpc = $rpc;
        return $rpc;
    }

}
