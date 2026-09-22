<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/1/5
 * Time: 15:10
 */

namespace app\outside\controllers;


use app\common\components\BaseController;
use app\common\exceptions\AppException;
use app\common\models\AccountWechats;
use app\outside\services\ClientService;
use Illuminate\Support\Facades\DB;

class IndexController extends BaseController
{
    public function index()
    {


        $client = new ClientService();
        $client->setRoute('goods/goods/index');
        $client->setData('sign_type', 'MD5');
        $client->setData('phone', 2455);
        $client->setData('order_sn', 'SN2404071646583225');
        $client->setData('arr', [2455,13422,212]);
        $result = $client->get('http://demo.yun.cn/outside/6/');
        dd($result);

        dd('测试');
    }

}