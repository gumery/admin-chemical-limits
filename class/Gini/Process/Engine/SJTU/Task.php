<?php

namespace Gini\Process\Engine\SJTU;

class Task
{
    public static function doUpdate($task, $description)
    {
        $instance = $task->instance;
        $requestData = (array)$instance->getVariable('data');

        $requestID = $requestData['request_id'];
        $request = a('inventory/request', $requestID);
        if (!$request->id || $request->status!=\Gini\ORM\Inventory\Request::STATUS_PENDING) return;

        return true;
    }
}
