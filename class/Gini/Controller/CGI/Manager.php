<?php

namespace Gini\Controller\CGI;

class Manager extends Layout\Board
{
    public function actionSetting()
    {
        $me = _G('ME');

        //没有存量管控权限
        if (!$me->id || !$me->isAllowedTo('设置存量上限')) {
            $this->redirect('error/401');
        }

        // //存量管控权限，同时是审核人员
        // if($me->isAllowedTo('审核上限申请')) {
        //     $this->redirect('error/401');
        // }

        $engine = \Gini\Process\Engine::of('default');
        $processName = \Gini\Config::get('app.chemical_approve_process');

        $process = $engine->getProcess($processName);
        $groups = $process->getGroups();

        $vars = [
            'me' => $me,
            'groups'=> $groups
        ];

        $this->view->body = V('inventory/setting', $vars);
    }
}
