<?php

namespace Gini\Controller\CGI\AJAX\Inventory;

class Request extends \Gini\Controller\CGI
{
    public function actionMore($page = 1, $type = 'requests')
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id) return;

        $page = max(1, $page);
        $form = $this->form();
        $q = $form['q'];
        $type = strtolower($type);

        if ($type=='approved') {
            return $this->_showMoreInstance($page, $q, $type);
        }

        return $this->_showMoreTask($page, $q);
    }

    public function actionGetOPDialog($id = 0)
    {
        if (!$this->_isAllowToOP()) return;

        $form = $this->form();
        $key = trim($form['key']);
        $id = trim($form['id']);

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/requests/request-op-dialog', [
            'id'=> $id,
            'key'=> $key,
            'title'=> $key=='approve' ? T('通过') : T('拒绝')
        ]));
    }

    public function actionPostOPRequest()
    {
        if (!$this->_isAllowToOP()) return;

        $form = $this->form('post');
        $key = $form['key'];
        $id = $form['id'];
        $note = $form['note'];

        list($process, $engine) = $this->_getProcessEngine();
        if (!$process->id) return;

        $task = $engine->getTask($id);
        if ($key=='approve') {
            $bool = $task->approve($note);
        } else {
            if ($note==='') return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                'code'=>  1,
                'message'=> T('请填写备注信息')
            ]);

            $bool = $task->reject($note);
        }

        $bool && $task->complete();

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'code' => $bool ? 0 : 1,
            'id'=> $id,
            'message' => $message ?: ($bool ? T('操作成功') : T('操作失败, 请您重试')),
        ]);
    }

    private function _isAllowToOP()
    {
        $me = _G('ME');
        $group = _G('GROUP');

        if (!$me->id || !$group->id || !$me->isAllowedTo('审核上限申请')) {
            return;
        }

        $form = $this->form();
        $key = $form['key'];
        $id = $form['id'];
        if (!$id || !in_array($key, ['approve', 'reject'])) return;

        list($process, $engine) = $this->_getProcessEngine();
        if (!$process->id) return;

        $task = $engine->getTask($id);
        if (!$task->id) return;
        if (!$task->candidate_group->id) return;

        $groups = $process->getGroups($me);

        foreach ($groups as $g) {
            if ($task->candidate_group->id==$g->id) {
                return true;
            }
        }
    }

    private function _showMoreInstance($page, $querystring=null, $type='pending')
    {
        $me = _G('ME');
        $group = _G('GROUP');
        $limit = 10;
        $start = ($page - 1) * $limit;

        list($process, $engine) = $this->_getProcessEngine();
        if (!$process->id) return;

        $user = $me->isAllowedTo('管理权限') ? null : $me;

        //如果需要审批 显示 各自组的 instance,如果不需要审批，显示所有 instance
        $conf = \Gini\config::get('chemical.requests_need_approve');
        if ($conf && $conf !== '${REQUESTS_NEED_APPROVE}') {
            $instances = $process->getInstances($start, $limit, $user);
        } else {
            $instances = $process->getInstances($start, $limit);
        }

        if (!count($instances)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/requests/list-none'));
        }
        $totalCount = $process->searchInstances($user);

        $objects = [];
        foreach ($instances as $instance) {
            $object = new \stdClass();
            $object->instance = $instance;
            $object->request = $this->_getInstanceData($instance);
            $object->status = $this->_getInstanceStatus($engine, $instance);
            $object->owner = a('user', $object->request['owner_id']);
            $object->group = a('group', $object->request['group_id']);
            $objects[$instance->id] = $object;
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/requests/list-instances', [
            'instances'=> $objects,
            'type'=> $type,
            'page'=> $page,
            'total'=> ceil($totalCount/$limit),
            'hazTypes' => \Gini\Config::get('haz.types')
        ]));
    }

    private function _showMoreTask($page, $querystring=null)
    {
        $me = _G('ME');
        $limit = 10;
        $start = ($page - 1) * $limit;

        list($process, $engine) = $this->_getProcessEngine();
        if (!$process->id) return;

        //如果需要审批 显示 各自组的 需要审批的 task,如果不需要审批，显示所有 task
        $conf = \Gini\config::get('chemical.requests_need_approve');
        if ($conf && $conf !== '${REQUESTS_NEED_APPROVE}') {
            $tasks = $engine->those('task')
                ->whose('process')->is($process)
                ->whose('candidate_group')->isIn($process->getGroups($me))
                ->whose('status')->is(\Gini\Process\ITask::STATUS_PENDING)
                ->orderBy('id', 'desc')
                ->limit($start, $limit);
        } else {
            $tasks = $engine->those('task')
                ->whose('process')->is($process)
                ->whose('status')->is(\Gini\Process\ITask::STATUS_PENDING)
                ->orderBy('id', 'desc')
                ->limit($start, $limit);
        }

        if (!count($tasks)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/requests/list-none'));
        }

        $requests = [];
        foreach ($tasks as $task) {
            $request = $this->_getInstanceData($task->instance);
            $request['task_status'] = $this->_getInstanceStatus($engine, $task->instance);
            $request['instance'] = $task->instance;
            $request['owner'] = a('user', $request['owner_id']);
            $request['group'] = a('group', $request['group_id']);
            $request['regent'] = $this->_getLimit($request);
            $requests[$task->id] = $request;
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/requests/list-tasks', [
            'requests'=> $requests,
            'type'=> $type,
            'page'=> $page,
            'total'=> ceil($tasks->totalCount()/$limit),
            'hazTypes' => \Gini\Config::get('haz.types')
        ]));
    }

    private function _getLimit($criteria=[])
    {
        $volume = null;
        $cas_no = trim($criteria['cas_no']);
        $group_id = (int) $criteria['group_id'];
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
        $types[] = $cas_no;
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
                $volumes[$reagent->cas_no] = $i->from($volume)->to($unit);
            }
        }
        sort($volumes);
        $volume = (string) current($volumes);
        return $volume==='' ? '' : $volume.$unit;
    }

    private function _getProcessEngine()
    {
        $processName = \Gini\Config::get('app.chemical_approve_process');
        $engine = \Gini\Process\Engine::of('default');
        $process = $engine->getProcess($processName);
        return [$process, $engine];
    }


    private function _getInstanceData($instance)
    {
        $data = $instance->getVariable('data');
        return $data;
    }

    private function _getInstanceStatus($engine, $instance)
    {
        if ($instance->status == \Gini\Process\IInstance::STATUS_END) {
            return T('已结束');
        }

        $task = $engine->those('task')
                ->whose('instance')->is($instance)
                ->orderBy('ctime', 'desc')
                ->orderBy('id', 'desc')->current();

        if (!$task->id) return T('正在初始化');

        if ($task->auto_callback) {
            switch ($task->status) {
            case \Gini\Process\ITask::STATUS_PENDING:
                return T('系统处理中');
                break;
            case \Gini\Process\ITask::STATUS_RUNNING:
                return T('系统处理中');
                break;
            case \Gini\Process\ITask::STATUS_APPROVED:
                return T('系统自动审批通过');
                break;
            case \Gini\Process\ITask::STATUS_UNAPPROVED:
                return T('系统自动拒绝');
                break;
            }
        } else {
            switch ($task->status) {
            case \Gini\Process\ITask::STATUS_PENDING:
                return T('等待 :group 审批', [
                    ':group'=> $task->candidate_group->title
                ]);
                break;
            case \Gini\Process\ITask::STATUS_RUNNING:
                return T(':group 正在审批', [
                    ':group'=> $task->candidate_group->title
                ]);
                break;
            case \Gini\Process\ITask::STATUS_APPROVED:
                return T(':group 审批通过', [
                    ':group'=> $task->candidate_group->title
                ]);
                break;
            case \Gini\Process\ITask::STATUS_UNAPPROVED:
                return T('被 :group 拒绝', [
                    ':group'=> $task->candidate_group->title
                ]);
                break;
            }
        }
    }

    public function actionPreview($instanceID)
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id) {
            return;
        }

        $processName = \Gini\Config::get('app.chemical_approve_process');
        $engine = \Gini\Process\Engine::of('default');
        $instance = $engine->fetchProcessInstance($processName, $instanceID);
        if (!$instance || !$instance->id) return;

        $tasks = $engine->those('task')
            ->whose('instance')->is($instance)
            ->whose('status')->isIn([
                \Gini\Process\ITask::STATUS_APPROVED,
                \Gini\Process\ITask::STATUS_UNAPPROVED,
            ])
            ->orderBy('ctime', 'desc');
        $data = [];
        foreach ($tasks as $task) {
            $data[$task->id] = $task;
        }

        $vars = [
            'tasks'=> $data
        ];

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/requests/preview', $vars));
    }
}
