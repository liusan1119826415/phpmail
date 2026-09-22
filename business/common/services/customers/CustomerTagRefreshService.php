<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */


namespace business\common\services\customers;


use business\common\services\customers\WechatCustomerRequestService;
use business\common\services\SettingService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Yunshop\WechatCustomers\common\models\CustomerFollowUserTag as CustomerTag;
use Yunshop\WechatCustomers\common\models\CustomerFollowUser as TagCustomer;
use Yunshop\MemberTags\Common\models\MemberTagsGroupModel as MemberTagGroup;
use Yunshop\MemberTags\Common\models\MemberTagsModel as MemberTag;
use Yunshop\WechatCustomers\common\models\CustomerTag as CustomerTagLog;
use Yunshop\MemberTags\Common\models\MemberTagsRelationModel as TagMember;
use Yunshop\WorkWechatTag\job\ChangeWechatMemberTagJob;
use Yunshop\WorkWechatTag\services\TagMemberService;
use Illuminate\Foundation\Bus\DispatchesJobs;

class CustomerTagRefreshService
{

    use DispatchesJobs;

    public $business_id;
    public $wechat_tag;
    public $connect_shop_tag;
    public $connect_shop_tag_group;
    public $connect_shop_disabled; //禁止同步商城
    public $connect_wechat;
    public $isset_tags;
    public $shop_tags;
    public $tag_ids;
    public $group_ids;
    public $clean_tag;

    public function __construct($business_id = 0, $connect_shop_disabled = 0)
    {
        $this->business_id = $business_id ?: SettingService::getBusinessId();
        $this->connect_shop_disabled = $connect_shop_disabled;
        $this->isConnectShop();
        $this->isConnectWechat();
    }


    public function isConnectWechat()
    {
        if (!app('plugins')->isEnabled('work-wechat-tag')) {
            $this->connect_wechat = false;
        } else {
            $this->connect_wechat = \Yunshop\WorkWechatTag\services\SettingService::isConnectWechat($this->business_id);
        }
        return $this->connect_wechat;
    }

    /*
     * 修改多个企业微信客户的标签
     */
    public function pushMemberTagToWechat($customer_ids, $add_tag = [], $remove_tag = [])
    {
        if (!is_array($customer_ids)) {
            return;
        }
        if (count($customer_ids) > 50) {
            $true_key = count($customer_ids) == count($customer_ids, 1) ? false : true;
            $customer_ids = array_chunk($customer_ids, 50, $true_key);
            foreach ($customer_ids as &$v) {
                $this->dispatch(new ChangeWechatMemberTagJob($this->business_id, $v, $add_tag, $remove_tag, \YunShop::app()->uniacid));
            }
        } else {
            (new ChangeWechatMemberTagJob($this->business_id, $customer_ids, $add_tag, $remove_tag))->handle();
        }
    }


    public function changeShopGroup($this_tag)
    {
        if (!$this->connect_shop_tag_group) {
            return null;
        }
        if ($this_tag->shop_tag_group_id) {
            $group = MemberTagGroup::uniacid()->find($this_tag->shop_tag_group_id);
        } elseif ($other_tag = CustomerTag::uniacid()->business($this->business_id)->where('group_id', $this_tag->group_id)->where('shop_tag_group_id', '<>', 0)->first()) {
            $group = MemberTagGroup::uniacid()->find($other_tag->shop_tag_group_id);
        }

        if (!$group) {
            $no_group_id = CustomerTag::uniacid()->business($this->business_id)->where('group_id', '<>', $this_tag->group_id)
                ->where('shop_tag_group_id', '<>', 0)->groupBy('shop_tag_group_id')->pluck('shop_tag_group_id')->toArray(); //去除已经关联其他微信标签组的数据
            if (!$group = MemberTagGroup::uniacid()->where('title', $this_tag->group_name)->whereNotIn('id', $no_group_id)->first()) {
                $group = new MemberTagGroup([
                    'uniacid' => \YunShop::app()->uniacid,
                ]);
            }
        }
        $group->title = $this_tag->group_name;
        $group->sort = $this_tag->group_order ?: 0;
        $group->save();

        $this_tag->shop_tag_group_id = $group->id;
        CustomerTag::uniacid()->business($this->business_id)->where('group_id', $this_tag->group_id)->update(['shop_tag_group_id' => $group->id]);
        return $group;
    }

    public function changeShopTag($this_tag)
    {
        if (!$this->connect_shop_tag) {
            return null;
        }
        $this_shop_tag = $this_tag->shop_tag_id ? MemberTag::uniacid()->find($this_tag->shop_tag_id) : null;
        if (!$this_shop_tag && !$this_shop_tag = $this->searchTag($this_tag, null, $this_tag->shop_tag_group_id)) {
            $this_shop_tag = new MemberTag([
                'uniacid' => \YunShop::app()->uniacid,
                'color' => '#000',
                'update_time' => '03:43:00',
                'type' => 1,
                'group_condition_type' => 1
            ]);
        }

        $this_shop_tag->title = $this_tag->name;
        $this_shop_tag->group_id = $this_tag->shop_tag_group_id ?: 0;
        $this_shop_tag->save();
        $this_tag->shop_tag_id = $this_shop_tag->id;
        return $this_shop_tag;
    }

    /*
     * 删除标签（同步删除 本地客户管理标签 和 商城标签 和 本地客户标签关联 和 商城会员标签关联）
     */
    public function deleteTag($tag)
    {
        $this->deleteShopTag($tag->shop_tag_id); //删除商城关联标签
        $connect_tag_member = TagCustomer::uniacid()->select('id')->business($this->business_id)->usable()->whereRaw('FIND_IN_SET( ?,tag_id)', [$tag->tag_id])->pluck('id')->toArray(); //获取拥有该标签的企业客户
        if (app('plugins')->isEnabled('work-wechat-tag') && $connect_tag_member) {
            TagMemberService::deleteTagIdOfCustomer($tag->tag_id, $connect_tag_member);
        }
        $tag->delete();
    }


    /*
     * 删除标签组（同步删除 本地客户管理标签组 和 商城标签组 和 本地客户标签关联 和 商城会员标签关联）
     */
    public function deleteTagGroup($group_id)
    {
        $tag_list = CustomerTag::uniacid()->where('group_id', $group_id)->get();
        if ($tag_list->isEmpty()) {
            return;
        }
        $tag_list->each(function ($v) {
            $this->deleteTag($v);
            $this->deleteShopTagGroup($v->shop_tag_group_id);
            $v->delete();
        });
    }


    /*
     * 根据企业微信接口返回数据同步企业微信标签数据
     */
    public function refreshTagByData($tag_data, $clean_tag = 0)
    {
        DB::beginTransaction();
        try {
            $this->wechat_tag = $tag_data;
            $this->clean_tag = $clean_tag;
            $this->cleanTagConnection();
            $this->changeWechatTag();
//            $this->addWechatLog();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 0, 'msg' => $e->getMessage(), 'data' => []];
        }
        return ['result' => 1, 'msg' => '成功', 'data' => []];
    }


    /*
     * 根据标签ID同步企业微信标签数据
     */
    public function refreshTag($tag_ids = [], $group_ids = [])
    {
        DB::beginTransaction();
        try {
            $this->tag_ids = $tag_ids;
            $this->group_ids = $group_ids;
            $this->clean_tag = $this->tag_ids || $this->group_ids ? 0 : 1;
            $this->getWechatTag(1, $this->tag_ids, $this->group_ids);
            $this->cleanTagConnection();
            $this->changeWechatTag();
            $this->addWechatLog();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 0, 'msg' => $e->getMessage(), 'data' => []];
        }
        return ['result' => 1, 'msg' => '成功', 'data' => []];
    }

    /*
     * 添加企业微信请求数据记录
     */
    public function addWechatLog()
    {
        CustomerTagLog::create([
            'uniacid' => \YunShop::app()->uniacid,
            'crop_id' => $this->business_id,
            'tag_group' => serialize($this->wechat_tag)
        ]);
    }


    /*
     * 根据企业微信返回的接口数据，清除本地关联了企业微信标签(组) 但是已经失效的数据
     */
    public function cleanTagConnection()
    {
        if (!$this->clean_tag) {
            return;
        }
        $group_arr = [];
        $tag_arr = [];
        foreach ($this->wechat_tag as &$v) {
            $group_arr[] = $v['group_id'];
            foreach ($v['tag'] as &$vv) {
                $tag_arr[] = $vv['id'];
            }
        }

        $delete_arr = CustomerTag::uniacid()->business($this->business_id)->where(function ($query) use ($group_arr, $tag_arr) {
            $query->whereNotIn('group_id', $group_arr)->orWhere(function ($query) use ($tag_arr) {
                $query->whereNotIn('tag_id', $tag_arr);
            });
        })->get();

        if ($delete_arr->isNotEmpty()) {
            $delete_arr->each(function ($v) {
                $this->deleteTag($v);
                $this->deleteShopTagGroup($v->shop_tag_group_id); //删除商城关联标签组
            });
        }

        CustomerTag::uniacid()->business($this->business_id)->whereNotIn('group_id', $group_arr)->delete(); //删除企业标签组
        CustomerTag::uniacid()->business($this->business_id)->whereNotIn('tag_id', $tag_arr)->delete();  //删除企业标签
        $this->clearRepeatTags($tag_arr); //清除重复的标签
    }


    /*
     * 删除商城标签数据
     */
    public function deleteShopTag($id)
    {
        if (!$this->connect_shop_tag_group || !$id) {
            return;
        }
        MemberTag::uniacid()->where('id', $id)->delete();
        TagMember::uniacid()->where('tag_id', $id)->delete();
    }

    /*
     * 删除商城标签组数据
     */
    public function deleteShopTagGroup($id = 0)
    {
        if (!$this->connect_shop_tag || !$id) {
            return;
        }
        MemberTagGroup::uniacid()->where('id', $id)->delete();
        MemberTag::uniacid()->where('group_id', $id)->update(['group_id' => 0]);
    }

    /*
     * 获取当前企业是否同步商城标签
     */
    public function isConnectShop()
    {
        $this->connect_shop_tag = false;
        $this->connect_shop_tag_group = false;
        if ($this->connect_shop_disabled) {
            return false;
        }
        if (!app('plugins')->isEnabled('work-wechat-tag')) {
            return false;
        } else {
            if (!\Yunshop\WorkWechatTag\services\SettingService::isConnectShop($this->business_id)) {
                return false;
            }
        }
        $this->connect_shop_tag = $this->connect_shop_tag_group = true;
        return $this->connect_shop_tag;
    }

    /*
     * 获取商城tag
     */
    public function getShopTag()
    {
        if (!app('plugins')->isEnabled('member-tags')) {
            return false;
        }
        $this->shop_tags = \Yunshop\MemberTags\Common\models\MemberTagsModel::uniacid()->get();
        return true;
    }

    /*
     * 获取本地企业标签
     */
    public function getBusinessTag()
    {
        $this->isset_tags = null;
        if (!app('plugins')->isEnabled('wechat-customers')) {
            return false;
        }
        $this->isset_tags = CustomerTag::uniacid()->where('crop_id', $this->business_id)->get();
        return true;
    }

    /*
     * todo 清除重复的标签
     */
    private function clearRepeatTags($tags = [])
    {
        foreach ($tags as $key => $item) {
            $count = CustomerTag::uniacid()->business($this->business_id)->where('tag_id', $item)->count();
            if ($count > 1) {
                $customerTag = CustomerTag::uniacid()->business($this->business_id)->where('tag_id', $item)->get();
                foreach ($customerTag as $tag) {
                    if ($count == 1) {
                        break;
                    }
                    $tag->delete();
                    $count = $count - 1;
                }
            }
        }
    }

    /*
     * 根据企业微信标签组数据 获取 商城关联标签组
     */
    private function getShopTagGroupByWechatGroup($wechat_tag_group)
    {
        if (!$this->connect_shop_tag_group) {
            return null;
        }

        $this_shop_group_id = $this->isset_tags->where('group_id', $wechat_tag_group['group_id'])->filter(function ($value, $key) {
            return $value->shop_tag_group_id != 0;
        })->first();
        $this_shop_group_id = $this_shop_group_id ? $this_shop_group_id->shop_tag_group_id : 0;
        if (!$this_shop_group = MemberTagGroup::uniacid()->find($this_shop_group_id)) {
            $isset_connect_id = $this->isset_tags->pluck('shop_tag_group_id')->toArray();
            if (!$this_shop_group = MemberTagGroup::uniacid()->where('title', $wechat_tag_group['group_name'])->whereNotIn('id', $isset_connect_id)->first()) {
                $key = 'tag_group_create_' . $wechat_tag_group['group_name'];
                if (Redis::setnx($key, 1)) {
                    Redis::expire($key, 5);
                } else {
                    sleep(8);
                    $this_shop_group = MemberTagGroup::uniacid()->where('title', $wechat_tag_group['group_name'])->whereNotIn('id', $isset_connect_id)->first();
                }
                if (!$this_shop_group) {
                    $this_shop_group = MemberTagGroup::create([
                        'uniacid' => \YunShop::app()->uniacid,
                        'title' => $wechat_tag_group['group_name'],
                        'sort' => intval($wechat_tag_group['order']) ?: 0,
                    ]);
                }
            }
        }
        return $this_shop_group;
    }


    /*
     * 修改标签和标签组数据
     */
    public function changeWechatTag($type = 0)
    {
        $this->getBusinessTag();
        foreach ($this->wechat_tag as &$v) {
            if ($v['deleted']) {
                $this->deleteTagGroup($v['group_id']);
                continue;
            }
            $v['shop_tag_group_id'] = 0;
            if ($this_shop_group = $this->getShopTagGroupByWechatGroup($v)) { //根据微信标签组查询商城标签组
                $this_shop_group->title = $v['group_name'];
                $this_shop_group->sort = $v['order'] ?: 0;
                $this_shop_group->save();
                $v['shop_tag_group_id'] = $this_shop_group->id;
            }

            $this->isset_tags->where('group_name', $v['group_name'])->where('group_id', '')->each(function ($tag) use ($v) {
                $tag->group_order = $v['order'];
                $tag->group_id = $v['group_id'];
                $tag->save();
            });

            $this->isset_tags->where('group_id', $v['group_id'])->each(function ($tag) use ($v) {
                $tag->group_order = $v['order'];
                $tag->group_id = $v['group_id'];
                $tag->group_name = $v['group_name'];
                $tag->save();
            });

            foreach ($v['tag'] as &$vv) {
                if ($vv['deleted']) {
                    $tag = CustomerTag::uniacid()->business($this->business_id)->where('tag_id', $vv['id'])->get();
                    $tag->each(function ($v) {
                        $this->deleteTag($v);
                    });
                    continue;
                }

                $this_tag = CustomerTag::uniacid()
                    ->where('name', $vv['name'])
                    ->where('group_id', $v['group_id'])
                    ->first();
                if (!$this_tag) {
                    if (!$this_tag = CustomerTag::uniacid()->where('tag_id', $vv['id'])->where('group_id', $v['group_id'])->first()) {
                        $key = 'tag_create_' . $v['group_id'] . '_' . $vv['id'];
                        if (Redis::setnx($key, 1)) {
                            Redis::expire($key, 5);
                        } else {
                            sleep(8);
                            $this_tag = CustomerTag::uniacid()->where('tag_id', $vv['id'])->where('group_id', $v['group_id'])->first();
                        }
                        if (!$this_tag) {
                            $this_tag = new CustomerTag([
                                'uniacid' => \YunShop::app()->uniacid,
                                'crop_id' => $this->business_id,
                                'group_id' => $v['group_id'],
                            ]);
                        }
                    }
                }


                $this_tag->fill([
                    'name' => $vv['name'],
                    'group_name' => $v['group_name'],
                    'order' => $vv['order'] ?: 0,
                    'tag_id' => $vv['id'],
                    'create_time' => $vv['create_time'],
                    'shop_tag_group_id' => $this_shop_group ? $this_shop_group->id : 0
                ]);

                $vv['shop_tag_id'] = 0;
                if (!$this->connect_shop_tag) {
                    $this_tag->save();
                    continue;
                }
                if (!$this_tag->shop_tag_id) {
                    $this_shop_tag = $this->changeShopTag($this_tag);
                    $this_tag->shop_tag_id = $this_shop_tag ? $this_shop_tag->id : 0;
                }
                $this_tag->save();
            }
        }
    }

    /*
     * 查询商城关联标签
     */
    private function searchTag($tag, $group = null, $group_id = 0)
    {
        if (!$group_id) {
            $group_id = $group ? $group->id : 0;
        }
        if ((!$member_tag = MemberTag::uniacid()->where('group_id', $group_id)->where('title', $tag->name)->first()) && $group_id) {
            $member_tag = MemberTag::uniacid()->where('group_id', 0)->where('title', $tag->name)->first();
        }
        return $member_tag;
    }


    /*
     * 检测标签是否存在
     */
    public function checkTagIsset($tag_id)
    {
        $res = $this->getWechatTag(0, [$tag_id]);
        if (!$res['result']) {
            return false;
        }
        if (!$tag = $res['result']['data'][0]['tag'][0]) {
            return false;
        }
        if ($tag['deleted'] || $tag['id'] != $tag_id) {
            return false;
        }
        return true;
    }

    /*
     * 请求接口获取企业微信标签数据
     */
    public function getWechatTag($type = 0, $tag_ids = [], $group_ids = [])
    {
        $this->wechat_tag = [];
        $res = (new WechatCustomerRequestService($this->business_id))->request('getCustomerList', [
            'tag_id' => $tag_ids,
            'group_id' => $group_ids
        ]);

        if ($res['result']) {
            $this->wechat_tag = $res['data']['tag_group'];
            if (!$type) {
                return ['result' => 1, 'msg' => '成功', 'data' => $this->wechat_tag];
            }
        } else {
            $msg = $res['msg'] ?: '获取企业微信客户标签失败,未知错误';
            if (!$type) {
                return ['result' => 0, 'msg' => $msg, 'data' => []];
            }
            throw new Exception($msg);
        }
    }

}
