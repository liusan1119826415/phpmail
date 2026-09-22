<?php
/****************************************************************
 * Author:  king -- LiBaoJia
 * Date:    12/25/20 4:11 PM
 * Email:   livsyitian@163.com
 * QQ:      995265288
 * IDE:     PhpStorm
 * User:
 ****************************************************************/


namespace app\backend\modules\point\controllers;


use app\backend\modules\member\models\Member;
use app\common\components\BaseController;
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
