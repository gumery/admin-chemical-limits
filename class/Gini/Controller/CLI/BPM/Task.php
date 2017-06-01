<?php

namespace Gini\Controller\CLI\BPM;

class Task extends \Gini\Controller\CLI
{
    public function actionRun($argv)
    {
        if (count($argv) == 0) return;
        $id = (int)$argv[0];
        $task = a('sjtu/bpm/process/task', $id);
        if (!$task->id) return;
        $conf = \Gini\Config::get('wechat.gateway');
        $templates = \Gini\Config::get('wechat.templates');
        $template = $templates['request-need-review'];
        $rpc = \Gini\IoC::construct('\Gini\RPC', $conf['api_url']);
        $token = $rpc->wechat->authorize($conf['client_id'], $conf['client_secret']);
        $templateID = $template['id'];
        $content = $template['content'];
        if (!$token) return;
        $group = $task->candidate_group;
        $instance = $task->instance;
        $data = (array)$instance->data;
        $data = $data['data'];

        $owner_id = $data['owner_id'];
        $owner = a('user', (int)$owner_id);
        $raw_data = [
            'title'=> [
                'color'=>'#173177',
                'value'=> '您有存量上限申请需要审核'
            ],
            'type' => [
                'color' => '#173177',
                'value' => '存量上线申请',
            ],
            'requester' => [
                'color' => '#173177',
                'value' => $owner->name,
            ],
            'request_date'=> [
                'color' => '#173177',
                'value' => $data['ctime'],
            ],
            'note' => [
                'color' => '#173177',
                'value' => $data['name'].' '.$data['reason'],
            ],
        ];
        $data = [];
        foreach ($content as $k => $v) {
            $data[$v] = $raw_data[$k];
        }
        $gus = those('sjtu/bpm/process/group/user')->whose('group')->is($group);
        foreach ($gus as $user) {
            $wechat_data =  (array)$user->wechat_data;
            if ($openID = $wechat_data['openid']) {
                $rpc->wechat->sendTemplateMessage($openID, $templateID, $data);
            }
        }
    }
}
