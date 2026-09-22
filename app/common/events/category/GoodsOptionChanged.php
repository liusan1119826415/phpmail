<?php
/**
 * Created by PhpStorm.
 * User: shanmu
 * Date: 2026/02/06
 * Time: 16:36
 */

namespace app\common\events\category;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
/**
 * 分类排序监听
 * Class CategorySortChanged
 */
class GoodsOptionChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public $optionIds;


    public function __construct($optionIds)
    {
        $this->optionIds = $optionIds;
      
        
    }           

}