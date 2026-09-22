<?php
/****************************************************************
 * Author:  king -- LiBaoJia
 * Date:    1/8/21 9:40 AM
 * Email:   livsyitian@163.com
 * QQ:      995265288
 * IDE:     PhpStorm
 * User:
 ****************************************************************/


namespace app\backend\modules\balance\controllers;


use app\common\components\BaseController;
use app\common\models\Member;
use app\common\services\ExportService;
use app\exports\traits\ExportTrait;

class MemberExportController extends BaseController
{
    use ExportTrait;

    public function index()
    {
        return $this->export();
    }
}
