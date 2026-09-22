<?php


namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\frontend\modules\project\services\QuoteService;
use Illuminate\Http\Request;

class QuoteController extends ApiController
{
    private QuoteService $quoteService;

    public function __construct(QuoteService $quoteService)
    {
        $this->quoteService = $quoteService;
        parent::__construct();
    }

    public function saveTemplate(Request $request)
    {
        $field_data = $request->input('field_data','[]');
        $jsonValue = json_decode($field_data, true);
        if(json_last_error() !== JSON_ERROR_NONE){
            return $this->errorJson("请传入正确的json格式");
        }
        $this->quoteService->saveTemplate($field_data);
        return $this->successJson('ok');
    }


    public function getTemplate(Request $request)
    {
        $list = $this->quoteService->getTemplate();
        return $this->successJson('ok',$list);
    }


}