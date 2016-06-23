<?php

namespace Gini\Controller\CGI;

class Inventory extends Layout\Board
{
    public function __index()
    {
        $this->redirect('inventory/reagents');
    }

    public function actionRequests()
    {
        $this->view->body = V('inventory/requests',[
        ]);
    }

    public function actionReagents()
    {
        $rgt_types = \Gini\ORM\Inventory::$rgt_types;
        $default_cas_nos = \Gini\ORM\Inventory\Reagent::$default_cas_nos;
        $defaults = [];

        foreach ($default_cas_nos as $cas => $v) {
            $reagent = those('inventory/reagent')->whose('cas_no')->is($cas)->andWhose('group_id')->is(null)->current();
            $subreagent = those('inventory/reagent')->whose('cas_no')->is($cas)->andWhose('group_id')->isNot(null);
            $defaults[$cas] = [
                'name'=> $v,
                'volume'=> $reagent->volume,
                'subs'=>$subreagent,
            ];
            
        }

        $enable = true;
        $setting = a('inventory/setting', ['key'=>'count_cart']);
        if ($setting->id) {
            $enable = $setting->enable;
        }

        $this->view->body = V('inventory/reagents',[
            'rgt_types'=> $rgt_types,
            'default_cas_infos'=> $defaults,
            'count_cart'=> $enable,
        ]);
    }

}
