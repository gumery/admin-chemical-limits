<?php

namespace Gini\Controller\CLI;

class Request extends \Gini\Controller\CLI
{
    public function actionFix()
    {
        $start = 0;
        $perpage =25;
        while (true) {
            $requests = Those('inventory/request')->Whose('status')->is(\Gini\ORM\Inventory\Request::STATUS_PENDING)->limit($start, $perpage);
            if (!count($requests)) break;
            $start+=$perpage;

            foreach ($requests as $request) {
                $tag = "chemical#{$request->id}";
                $instance = a('sjtu/bpm/process/instance', ['tag' => $tag]);
                if ($instance->id) {
                    continue;
                }

                $bool = self::_createInstance($request);

                if ($bool) {
                    echo '.';
                    continue;
                }

                echo $request->id."---fail \n";
            }
        }

        echo "DONE \n";
    }

    private static function _createInstance($request)
    {
        $cols = [];
        $cols['type'] = $request->type;
        $cols['cas_no'] = $request->cas_no;
        $cols['volume'] = $request->volume;
        $cols['group_id'] = $request->group->id;
        $cols['owner_id'] = $request->owner->id;
        $cols['reason'] = $request->reason;
        $cols['ctime'] = $cols['mtime'] = date('Y-m-d H:i:s');

        $id = $request->id;
        $processName = \Gini\Config::get('app.chemical_approve_process');
        $engine = \Gini\Process\Engine::of('default');
        $instanceID = self::_getChemicalInstanceID($processName, $id);

        $cols['request_id'] = $id;
        $cacheData['data'] = $cols;
        if ($instanceID) {
            $instance = $engine->fetchProcessInstance($processName, $instanceID);
            if (!$instance->id || $instance->status==\Gini\Process\IInstance::STATUS_END) {
                $instance = $engine->startProcessInstance($processName, $cacheData, "chemical#{$id}");
            }
        } else {
            $instance = $engine->startProcessInstance($processName, $cacheData, "chemical#{$id}");
        }

        if ($instance->id && $instance->id!=$instanceID) {
            self::_setChemicalInstanceID($processName, $id, $instance->id);
            $request->instanceID = $instance->id;
            if (!$request->save(true)) {
                return false;
            }
        }

        return true;
    }

    private static function _getChemicalInstanceID($processName, $id)
    {
        $key = "chemical#{$id}";
        $info = (array)\Gini\TagDB\Client::of('default')->get($key);
        //$info = [ 'bpm'=> [ $processName=> [ 'instances'=> [ $instanceID, $latestinstanceid ] ] ] ]
        $info = (array)@$info['bpm'][$processName]['instances'];
        return array_pop($info);
    }

    private static function _setChemicalInstanceID($processName, $id, $instanceID)
    {
        $key = "chemical#{$id}";
        $info = (array)\Gini\TagDB\Client::of('default')->get($key);
        $info['bpm'][$processName]['instances'] = @$info['bpm'][$processName]['instances'] ?: [];
        array_push($info['bpm'][$processName]['instances'], $instanceID);
        \Gini\TagDB\Client::of('default')->set($key, $info);
    }
}
