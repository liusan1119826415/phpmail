<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/2
 * Time: 下午5:09
 */

namespace app\frontend\models;


use app\common\exceptions\AppException;

use app\frontend\modules\member\models\MemberAddress;
use app\frontend\modules\member\models\YzMemberAddress;
use app\frontend\modules\member\services\MemberService;
use app\frontend\modules\memberCart\MemberCartCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
/**
 * Class MemberCart
 * @package app\frontend\models
 * @property Goods goods
 * @property GoodsOption goodsOption

 */
class MemberCart extends \app\common\models\MemberCart
{
    protected $fillable = [];

    protected $guarded = ['id'];
    protected $hidden = ['member_id', 'uniacid','updated_at','deleted_at'];


    public static function getCartNum($member_id)
    {
        if (!$member_id) {
            return 0;
        }

        return static::uniacid()->where('member_id', $member_id)->count();
    }

    /**
     * 根据购物车id数组,获取购物车记录数组
     * @param $cartIds
     * @return mixed
     */
    public static function getCartsByIds($cartIds)
    {
        if (!is_array($cartIds)) {
            $cartIds = explode(',', $cartIds);
        }
        $result = static::whereIn('id', $cartIds)
            ->get();

        return $result;
    }

    public function scopeFilterFailureGoods(Builder $query)
    {
        return $query->join('yz_goods', 'yz_goods.id', '=', 'yz_member_cart.goods_id')
        ->where('yz_goods.status',1)
        ->whereNull('yz_goods.deleted_at');
    }

    public function scopeCarts(Builder $query)
    {
        return $query->select($this->getTable().'.*')->uniacid()
            ->with(['goods' => function ($query) {
                return $query->withTrashed()->select('id', 'thumb', 'price', 'market_price', 'title','sku', 'deleted_at','plugin_id','stock','status','has_option','structure','old_option','productType');
            }])
            ->with(['goodsOption' => function ($query) {
                //return $query->whereHas('goods')->select('id', 'goods_id','title', 'thumb', 'product_price', 'market_price','stock');
                return $query->whereHas('goods')->select('id', 'goods_id','title', 'thumb', 'product_price', 'market_price','stock','length','width','height','product_model','cad_plan_model','structure','thumb_url');
            }]);
    }


    public function scopeFloor(Builder $query)
    {
        return $query->select($this->getTable().'.*')->uniacid()
            ->with(['goods' => function ($query) {
                return $query->withTrashed()->select('id', 'thumb', 'price', 'market_price', 'title','sku', 'deleted_at','plugin_id','stock','status','has_option','structure',"real_image","e_catalog_pdf","pdf_page","pdf_page_img","pdf_high_img","supp_id","atlas","productType","old_option")->with(['supplierGoods'=>function($query){
                    $query->select("id","store_name");
                }]);
            }])
            ->with(['goodsOption' => function ($query) {
                //return $query->whereHas('goods')->select('id', 'goods_id','title', 'thumb', 'product_price', 'market_price','stock');
                return $query->whereHas('goods')->select('id', 'goods_id','title', 'thumb', 'product_price', 'market_price','stock','length','width','height','product_model','volume','structure');
            }])
            ->with(['belongsToFloor' => function ($query) {
                return $query->select('id', 'name as floor_name');
            }])
            ->with(['belongsToSpace' => function ($query) {
                return $query->select('id', 'name as space_name');
            }]);
    }


    public function goodsOption()
    {
        return $this->belongsTo(app('GoodsManager')->make('GoodsOption'), 'option_id');
    }

    /**
     * Get a list of members shopping cart through cart IDs
     *
     * @param array $cartIds
     *
     * @return array
     * */
    public static function getMemberCartByIds($cartIds)
    {
        return static::uniacid()->whereIn('id', $cartIds)->get()->toArray();
    }

    /**
     * Add merchandise to shopping cart
     *
     * @param array $data
     *
     * @return 1 or 0
     * */
    public static function storeGoodsToMemberCart($data)
    {
        //需要监听事件，购物车存在的处理方式
        return static::insert($data);
    }

    /**
     * 检测商品是否存在购物车
     *
     * @param array $data ['member_id', 'goods_id', 'option_id']
     *
     * @return self | false
     * */
    public static function hasGoodsToMemberCart($data)
    {

       //DB::connection()->enableQueryLog();
        $hasGoods = self::uniacid()
            ->where([
                'member_id' => $data['member_id'],
                'goods_id' => $data['goods_id'],
                'option_id' => $data['option_id'],
                'space_id'=>$data['space_id'],
                'components_hash'=>$data['components_hash']
            ])
            ->first();
       // dump(DB::getQueryLog());die;
        return $hasGoods ? $hasGoods : false;
    }

    /**
     * 定义字段名
     *
     * @return array
     */
    public function atributeNames()
    {
        return [
            'goods_id' => '未获取到商品',
            'total' => '商品数量不能为空',
        ];
    }

    /**
     * 字段规则
     *
     * @return array
     */
    public function rules()
    {
        return [
            'goods_id' => 'required',
            'total' => 'required',
        ];
    }

    /**
     * @return MemberCartCollection
     * @throws AppException
     */
    protected function getAllMemberCarts(){
        return (new MemberCartCollection(Member::current()->memberCarts));
    }


    public function goods()
    {
        return $this->belongsTo(app('GoodsManager')->make('Goods'));
    }

    public function hasManyAddress()
    {
        return $this->hasMany(MemberAddress::class,"uid","member_id");
    }

    public function hasManyMemberAddress()
    {
        return $this->hasMany(YzMemberAddress::class,"uid","member_id");
    }
}
