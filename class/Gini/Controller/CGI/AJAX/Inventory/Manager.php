<?php

namespace Gini\Controller\CGI\AJAX\Inventory;

class Manager extends \Gini\Controller\CGI
{
    public function actionMoreMembers($start = 0)
    {
        $group = _G('GROUP');
        $me = _G('ME');
        if (!$group->id || !$me->isAllowedTo('管理权限')) {
            return false;
        }

        $per_page = 20;
        $next_start = $start + $per_page;

        $form = $this->form();
        $members = thoseIndexed('user')->filter(['query' => $form['q']])->fetch($start, $per_page);

        $vars = [
            'members' => $members,
            'start' => $start,
            'next_start' => $next_start,
        ];

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/manager/members', $vars));
    }

    public function actionGetDialog()
    {
        $group = _G('GROUP');
        $me = _G('ME');

        if (!$group->id || !$me->isAllowedTo('管理权限')) {
            $data['error'] = T('您无权进行该操作');
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
        }

        $form = $this->form();
        $engine = \Gini\Process\Engine::of('default');
        $processName = \Gini\Config::get('app.chemical_approve_process');
        $process = $engine->getProcess($processName);
        $group  = $process->getGroup($form['group']);
        if (!$group->id) {
            $data['error'] = T('操作失败');
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
        }

        $data['success'] = T('您确定要删除么？ :group ', [':group' => $group->title]);
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
    }

    public function actionRemoveGroup()
    {
        $group = _G('GROUP');
        $me = _G('ME');
        if (!$group->id || !$me->isAllowedTo('管理权限')) {
            return false;
        }

        $form = $this->form('post');
        if (!isset($form['group'])) return false;

        $engine = \Gini\Process\Engine::of('default');
        $processName = \Gini\Config::get('app.chemical_approve_process');
        $process = $engine->getProcess($processName);

        $name = $form['group'];
        $success = $process->removeGroup($name);

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $success ? true : T('操作失败, 请重试'));
    }

    public function actionAddGroup()
    {
        $group = _G('GROUP');
        $me = _G('ME');

        if (!$group->id || !$me->isAllowedTo('管理权限')) {
            return false;
        }

        return self::_showEditGroupForm();
    }

    public function actionEditGroup()
    {
        $group = _G('GROUP');
        $me = _G('ME');
        if (!$group->id || !$me->isAllowedTo('管理权限')) {
            return false;
        }
        $form = $this->form();
        if (!isset($form['group'])) return false;

        $engine = \Gini\Process\Engine::of('default');
        $processName = \Gini\Config::get('app.chemical_approve_process');
        $process = $engine->getProcess($processName);
        $group  = $process->getGroup($form['group']);

        if (!$group->id) return false;

        return self::_showEditGroupForm([
            'group'=> $group
        ]);
    }

    private static function _showEditGroupForm(array $data=[])
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', (string)V('inventory/manager/edit-group-form', $data));
    }

    public function actionSubmitGroup()
    {
        $group = _G('GROUP');
        $me = _G('ME');

        if (!$group->id || !$me->isAllowedTo('管理权限')) {
            return false;
        }

        $post = $this->form('post');
        $rawname = $post['rawname'];
        $name = $post['name'];
        $title = $post['title'];
        $description = $post['description'];

        $validator = new \Gini\CGI\Validator();
        try {
            $validator
                ->validate('name', $name, T('请填写分组标识'))
                ->validate('title', $title, T('请填写分组名称'))
                ->done();
        } catch (\Gini\CGI\Validator\Exception $e) {
            $errors = $validator->errors();
        }

        if (!empty($errors)) {
            return self::_showEditGroupForm([
                'errors'=> $errors,
                'form'=> $post
            ]);
        }

        $engine = \Gini\Process\Engine::of('default');
        $processName = \Gini\Config::get('app.chemical_approve_process');

        $process = $engine->getProcess($processName);

        if ($rawname) {
            $group  = $process->getGroup($rawname);
            $method = 'updateGroup';
            if (!$group->id) {
                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('操作失败'));
            }
            $newgroup  = $process->getGroup($name);
            if ($newgroup->id && $newgroup->id!=$group->id) {
                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('操作失败, 分组标识已被占用'));
            }
        } else {
            $group  = $process->getGroup($name);
            $method = 'addGroup';
            if ($group->id) {
                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('操作失败, 分组标识已被占用'));
            }
        }

        $bool = $process->$method($name, [
            'title'=> $title,
            'description'=> $description
        ]);

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $bool ? true : T('操作失败'));
    }


    //设置权限
    public function actionAddUser($pname = '')
    {
        $group = _G('GROUP');
        $me = _G('ME');
        if (!$group->id || !$me->isAllowedTo('管理权限')) {
            return false;
        }

        $form = $this->form('post');
        if (!isset($form['id'])) return false;

        $user = a('user', $form['id']);
        if (!$user->id) return false;

        $engine = \Gini\Process\Engine::of('default');
        $processName = \Gini\Config::get('app.chemical_approve_process');
        $process = $engine->getProcess($processName);
        $group  = $process->getGroup($pname);

        if (!$group->id) return false;

        $success = !!$group->addUser($user);
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $success);
    }


    //移除权限
    public function actionRemoveUser($pname = '')
    {
        $group = _G('GROUP');
        $me = _G('ME');
        if (!$group->id || !$me->isAllowedTo('管理权限')) {
            return false;
        }

        $form = $this->form('post');
        if (!isset($form['id'])) return false;

        $user = a('user', $form['id']);
        if (!$user->id) return false;

        $engine = \Gini\Process\Engine::of('default');
        $processName = \Gini\Config::get('app.chemical_approve_process');
        $process = $engine->getProcess($processName);
        $group  = $process->getGroup($pname);

        if (!$group->id) return false;

        $success = !!$group->removeUser($user);
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $success);
    }

}
