<?php
/**
 * Created by PhpStorm.
 * 
 *
 *
 * Date: 2022/8/3
 * Time: 16:53
 */

namespace business\common\notice;


use business\common\models\MessageNotice;
use Illuminate\Container\Container;
use Illuminate\Support\Str;

class BusinessNoticeManager extends Container
{
    public function __construct()
    {
        $this->bind('MessageNotice',function ($messageNotice,$attributes = []) {
            return new MessageNotice($attributes);
        });

        $this->singleton('ProductCollection',function ($messageNotice) {
            return new NoticeProductCollection();
        });


        $this->setShopProduct();
    }

    private function setShopProduct()
    {
       $this->make('ProductCollection')->bind('shop', function ($manager, $params) {
            return new BusinessShopNotice();
        });
    }

    public function getAllProduct()
    {
        $allProduct = collect([]);

        $productContainer = app('BusinessMsgNotice')->make('ProductCollection')->getBindings();

        foreach ($productContainer as $key => $value) {
            $allProduct->push($this->getProduct($key));
        }

        return $allProduct->filter()->values();
    }

    /**
     * @param $code
     * @return BusinessMessageNoticeBase|bool
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function getProduct($code)
    {
        if (app('BusinessMsgNotice')->make('ProductCollection')->bound($code)) {
            return app('BusinessMsgNotice')->make('ProductCollection')->make($code);
        }

        return false;
    }

}