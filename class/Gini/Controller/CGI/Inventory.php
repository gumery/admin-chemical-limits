<?php

namespace Gini\Controller\CGI;

class Inventory extends Layout\Board
{
    public function __index()
    {
        $me = _G('ME');
        if (!($me->isAllowedTo('设置存量上限') || $me->isAllowedTo('审核上限申请'))) {
            $this->redirect('error/401');
        }

        $this->redirect('inventory/reagents');
    }

    public function actionRequests($page=1)
    {
        $me = _G('ME');
        if (!($me->isAllowedTo('设置存量上限') || $me->isAllowedTo('审核上限申请'))) {
            $this->redirect('error/401');
        }

        $this->view->body = V('inventory/requests',[
            'requests'=> \Gini\CGI::request("ajax/inventory/request/more/{$page}/requests", $this->env)->execute()->content()
        ]);
    }

    public function actionApproved($page=1)
    {
        $me = _G('ME');
        if (!($me->isAllowedTo('设置存量上限') || $me->isAllowedTo('审核上限申请'))) {
            $this->redirect('error/401');
        }

        $this->view->body = V('inventory/approved',[
            'instances'=> \Gini\CGI::request("ajax/inventory/request/more/{$page}/approved", $this->env)->execute()->content()
        ]);
    }

    public function actionReagents()
    {
        $me = _G('ME');
        if (!($me->isAllowedTo('设置存量上限') || $me->isAllowedTo('审核上限申请'))) {
            $this->redirect('error/401');
        }

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
