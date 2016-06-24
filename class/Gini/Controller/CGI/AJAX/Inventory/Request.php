<?php

namespace Gini\Controller\CGI\AJAX\Inventory;

class Request extends \Gini\Controller\CGI
{
    public function actionMore($page=1)
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;

        $perpage = 25;
        $page = max(1, $page);
        $start = max(0, $page-1) * $perpage;

        $query = those('inventory/request');
        $count = $query->totalCount();
        $requests = $query->limit($start, $perpage);

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/requests-list', [
            'page'=> $page,
            'total'=> ceil($count / $perpage),
            'requests'=> $requests
        ]));
    }
}

