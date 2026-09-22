<?php

namespace app\backend\controllers;

use app\common\components\BaseController;
use app\common\services\ComponentService;

class ComponentController extends BaseController
{

    /**
     * 跳转页面的链接
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJumpLink()
    {
        $data['jump_link'] = ComponentService::getComponentList();
        return $this->successJson('ok',$data);
    }
}
