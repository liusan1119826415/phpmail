<?php
/**
 * Created by PhpStorm.
 * Author:  
 * Date: 2017/4/17
 * Time: 下午6:10
 */

namespace app\common\exceptions;

use Exception;

class ShopException extends Exception
{
    public $redirect = '';
    public $data = [];

    const UNIACID_NOT_FOUND = -2; // 公众号id不存在

    public function setRedirect($redirect)
    {
        $this->redirect = $redirect;
    }

    public function __construct($message = "", $data = [], $redirect = '',$code=0)
    {

        $this->data = $data;
        $this->redirect = $redirect;

        parent::__construct($message, $code);
    }

    public function getData()
    {
        return $this->data;
    }

    private function getDefaultData()
    {
        return ['code' => $this->getCode()];
    }

    public function getRedirect()
    {
        return $this->redirect;
    }

    public function getStatusCode()
    {
        return 200;
    }

}