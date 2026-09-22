<?php


namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\common\exceptions\AppException;
use app\frontend\modules\project\services\SearchHistoryService;
class SearchHistoryController extends  ApiController
{
    /**
     * 获取用户搜索历史
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $member_id = \YunShop::app()->getMemberId();

            $key_type = request()->key_type?:1;
            $history = SearchHistoryService::getSearchHistory($key_type,$member_id);

            return $this->successJson('ok',$history);
        } catch (\Exception $e) {
            return $this->errorJson("获取搜索历史失败");

        }
    }

    /**
     * 清除用户搜索历史
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clear()
    {
        try {
            $member_id = \YunShop::app()->getMemberId();
            $key_type = request()->key_type?:1;
            $clear_type = request()->clear_type?:"all";
            $keyword = request()->keyword;
            if($clear_type == "all"){
                $result = SearchHistoryService::clearSearchHistory($key_type,$member_id);
            }else{
                if(!$keyword){
                    throw new AppException("关键词必须");
                }
                $result = SearchHistoryService::removeSingleKeyword($key_type,$keyword,$member_id);
            }


            if ($result) {
                return $this->successJson('ok');
            } else {
                return $this->errorJson("清除搜索历史失败");
            }
        } catch (\Exception $e) {
            return $this->errorJson("清除搜索历史失败");
        }
    }





}