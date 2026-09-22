<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/8
 * Time: 16:35
 */

namespace app\backend\modules\withdraw\controllers;


use app\backend\modules\withdraw\services\WithdrawSetService;
use app\common\components\BaseController;

class WithdrawSetController extends BaseController
{
    public function index()
    {


        $data = (new WithdrawSetService())->view();

        return view('withdraw.widget.vue-index', [
            'data' => json_encode($data),
        ])->render();
    }


    public function submitSave()
    {
        $result =  (new WithdrawSetService())->setSave();
        return $this->successJson('成功');
    }
}
