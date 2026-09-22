<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */

namespace business\common\job;


use app\common\facades\Setting;
use business\common\services\customers\ShopTagRefreshService;
use business\common\services\DepartmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class PushMemberTagJob implements ShouldQueue
{

    use InteractsWithQueue, Queueable, SerializesModels;
    protected $business_id;
    protected $uniacid;
    protected $uids;

    public function __construct($uniacid, $business_id, $uids)
    {
        $this->uniacid = $uniacid;
        $this->business_id = $business_id;
        $this->uids = $uids;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \YunShop::app()->uniacid = $this->uniacid;
        Setting::$uniqueAccountId = $this->uniacid;
        \Log::debug("用户标签推送队列开始_{$this->business_id}", $this->uids);
        (new ShopTagRefreshService($this->business_id))->connectShopTagMemberToWechat($this->uids);
        \Log::debug("用户标签推送队列结束_{$this->business_id}", $this->uids);

    }


}