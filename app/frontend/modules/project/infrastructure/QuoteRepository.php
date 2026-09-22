<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\ShopException;


use app\frontend\modules\project\repositories\QuoteRepositoryInterface;
use app\frontend\modules\project\models\MemberQuoteTemplate;

class QuoteRepository extends BaseRepository implements QuoteRepositoryInterface
{


    public function saveTemplate(string $field_data): bool
    {
        try {

            $data = [
                "member_id"=>\YunShop::app()->getMemberId(),
                "field_data"=>$field_data
            ];
            MemberQuoteTemplate::saveTemplate($data);
            return true;
        }catch (\Exception $e){
            throw new ShopException($e->getMessage());
        }

    }


    public function getTemplate():array
    {
        $template = MemberQuoteTemplate::select("id","field_data")->where('member_id',\YunShop::app()->getMemberId())->first();
        if($template){
            $template = $template->toArray();
            $template['field_data'] = json_decode($template['field_data'],true);
        }else{
            $template = [];
        }

        return $template;

    }


}