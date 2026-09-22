<?php
//Route::any('/', function () {
//    return true;
//});

Route::group(['prefix' => 'outside/{uniacid}'], function () {  //插件路由
    Route::get('index','controllers\IndexController@index');
    Route::get('address','controllers\AddressController@getAddress');
    Route::any('upload','controllers\UploadController@index'); //文件上传

    Route::get('member/level','modules\member\controllers\MemberLevelController@index');
    Route::get('member/info/query','modules\member\controllers\InfoController@query');
    Route::get('member/info/detail','modules\member\controllers\InfoController@detail');
    Route::get('member/address','modules\member\controllers\AddressController@index');

    Route::post('member/info/update','modules\member\controllers\InfoController@update');
    Route::post('member/create','modules\member\controllers\InfoController@create');


    Route::get('goods/goods/index','modules\goods\controllers\GoodsController@index');
    Route::get('goods/list','modules\goods\controllers\GoodsController@list');

    Route::post('order/buy','modules\order\controllers\BuyController@index');
    Route::any('order/list','modules\order\controllers\ListController@index');
    Route::get('order/page','modules\order\controllers\ListController@page');

    //  商城分单问题，第三方下单一个请求号会分成多笔订单
    Route::post('order/create','modules\order\controllers\CreateController@index');


    //商城优惠券
    Route::get('coupon/list','modules\coupon\controllers\CouponController@list');
    Route::post('coupon/send','modules\coupon\controllers\SendOrReceiveController@send');
    Route::post('coupon/receive','modules\coupon\controllers\SendOrReceiveController@receive');

    //商城资产
    Route::post('assets/pointChange','modules\assets\controllers\AssetsController@pointChange');
    Route::post('assets/balanceChange','modules\assets\controllers\AssetsController@balanceChange');
});

