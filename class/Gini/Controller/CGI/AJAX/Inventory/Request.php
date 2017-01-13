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

        $query = those('inventory/request')->orderBy('ctime', 'desc');
        $count = $query->totalCount();
        $requests = $query->limit($start, $perpage);

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/requests-list', [
            'page'=> $page,
            'total'=> ceil($count / $perpage),
            'requests'=> $requests
        ]));
    }

    public function actionGetOPDialog()
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;
        $form = $this->form();
        $type = trim($form['type']);
        $requestID = trim($form['id']);

        $types = [
            'approve'=> '批准申请', 
            'reject'=> '拒绝申请'
        ];
        if (!in_array($type, array_keys($types))) return;

        $request = a('inventory/request', $requestID);
        if (!$request->id || $request->status!=\Gini\ORM\Inventory\Request::STATUS_PENDING) return;

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('inventory/request-op-dialog', [
            'type'=> $type,
            'title'=> $types[$type],
            'request'=> $request
        ]));
    }

    public function actionPostOPRequest()
    {
        $me = _G('ME');
        $group = _G('GROUP');
        if (!$me->id || !$group->id || !$me->isAllowedTo('设置存量上限')) return;

        $form = $this->form('post');
        $type = trim($form['type']);
        $requestID = trim($form['id']);
        $note = trim($form['note']);

        if ($note==='') return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'code'=>  1,
            'message'=> T('请填写备注信息')
        ]);

        $request = a('inventory/request', $requestID);
        if (!$request->id || $request->status!=\Gini\ORM\Inventory\Request::STATUS_PENDING) return;

        switch ($type) {
        case 'approve':
            $db = \Gini\Database::db();
            try {
                $db->beginTransaction();
                $request->approve_time = $request->mtime = date('Y-m-d H:i:s');
                $request->approve_man = $me;
                $request->approve_note = $note;
                $request->status = \Gini\ORM\Inventory\Request::STATUS_APPROVED;
                $bool = $request->save();
                if (!$bool) throw new \Exception();

                if ($request->cas_no) {
                    $chemicalInfo = \Gini\ChemDB\Client::getChemicalInfo($request->cas_no);
                    if (empty($chemicalInfo)) {
                        throw new \Exception();
                    }
                }

                $pa = those('inventory/reagent')->whose('cas_no')->is($request->cas_no ?: $request->type)->andWhose('group_id')->is(null)->current();
                if (!$pa->id) {
                    $pa = a('inventory/reagent');
                    $pa->cas_no = $request->cas_no ?: $request->type;
                    if (!empty($chemicalInfo)) {
                        $pa->name = $chemicalInfo['name'];
                        $pa->types = implode(',', $chemicalInfo['types']);
                        $pa->state = $chemicalInfo['state'];
                    }
                    if (!$pa->save()) {
                        throw new \Exception();
                    }
                }

                $reagent = a('inventory/reagent', [
                    'cas_no'=> $request->cas_no ?: $request->type,
                    'group'=> $request->group
                ]);

                if (!$reagent->id) {
                    $reagent->group = $request->group;
                    $reagent->cas_no = $request->cas_no ?: $request->type;
                }

                if (!empty($chemicalInfo)) {
                    $reagent->name = $chemicalInfo['name'];
                    $reagent->types = implode(',', $chemicalInfo['types']);
                    $reagent->state = $chemicalInfo['state'];
                }

                $reagent->volume = $request->volume;
                $bool = $reagent->save();
                if (!$bool) throw new \Exception();

                $db->commit();
                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                    'code'=> 0,
                    'message'=> T('修改成功'),
                    'id'=> $request->id,
                    'text'=> T('已通过'),
                ]);
            }
            catch (\Exception $e) {
                $db->rollback();
            }
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                'code'=> 1,
                'message'=> T('操作失败，请您重试')
            ]);
            break;
        case 'reject':
            $request->reject_time = $request->mtime = date('Y-m-d H:i:s');
            $request->reject_man = $me;
            $request->status = \Gini\ORM\Inventory\Request::STATUS_REJECTED;
            $request->reject_note = $note;
            $bool = $request->save();
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
                'code'=> $bool ? 0 : 1,
                'message'=> $bool ? T('操作成功') : T('操作失败，请您重试'),
                'id'=> $bool ? $request->id : 0,
                'text'=> $bool ? T('已拒绝') : '',
            ]);
            break;
        default:
        }
    }
}

