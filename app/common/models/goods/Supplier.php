<?php


namespace app\common\models\goods;

use app\backend\modules\member\models\MemberUnique;
use app\common\models\Address;
use app\common\models\BaseModel;
use app\common\models\kefu\ServiceGroup;
use app\common\models\kefu\ServiceUser;
use app\common\models\McMappingFans;
use app\common\models\MemberMiniAppModel;
use app\frontend\modules\goods\models\Comment;
use app\common\models\Street;
use Yunshop\PackageDeliver\model\Deliver;
use Yunshop\Supplier\common\Observer\SupplierObserver;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Yunshop\Supplier\common\services\apply\ApplyInfoService;

/**
 * Class Supplier
 * @package Yunshop\Supplier\common\models
 * @property int member_id
 * @property string username
 * @property string password
 * @property string realname
 * @property string mobile
 * @property int status
 * @property int uniacid
 * @property string salt
 * @property string product
 * @property string remark
 * @property int uid
 * @property string logo
 * @property string company_bank
 * @property string company_ali
 * @property string ali
 * @property string wechat
 * @property int diyform_data_id
 * @property string bank_username
 * @property string bank_of_accounts
 * @property string opening_branch
 * @property string company_ali_username
 * @property string ali_username
 * @property string province_name
 * @property string city_name
 * @property string district_name
 * @property int grade
 * @property string store_name
 */
class Supplier extends BaseModel
{
    public $table = 'yz_supplier';
    protected $guarded = [''];
    protected $search_fields = ['id', 'username'];
    protected $hidden = ['password'];
    protected $appends = ['supplier_id'];
    protected $casts = [
        'base_info' => 'json',
        'legal_person_info' => 'json',
        'account_info' => 'json',
       // 'certificate' => 'json',
    ];

    const PLUGIN_ID = 92;

    const WAIT_APPLY_STATUS =1;  //1等待审核结果
    const IMPROVE_APPLY_STATUS = 2;//2 完善店铺信息
    const SHOP_LINE = 3;  //3 店铺上线
    const WAIT_STATUS = 0;  //待审核
    const SUCCESS_STATUS = 1;  //通过
    const REJECT_STATUS = 2; //拒绝

    public function getSupplierIdAttribute()
    {
        return $this->attributes['supplier_id'] = $this->attributes['id'];
    }


    public function serviceGroup()
    {
        return $this->hasMany(ServiceGroup::class,'relation','id');
    }

    public static function getLicenseTypeName($license_type)
    {
        foreach (self::getLicenseType() as $value) {
            if ($value['id'] == $license_type) {
                return $value['name'];
            }
        }
        return '';
    }

    public static function getLicenseValidTypeName($license_valid_type)
    {
        foreach (self::getValidType() as $value) {
            if ($value['id'] == $license_valid_type) {
                return $value['name'];
            }
        }
        return '';
    }



    public function goods()
    {
        return $this->hasMany(Goods::class, 'supp_id', 'id');
    }

    public static function getLegalValidTypeName($legal_valid_type_name)
    {
        foreach (self::getValidType() as $value) {
            if ($value['id'] == $legal_valid_type_name) {
                return $value['name'];
            }
        }
        return '';
    }

    public static function getBankTypeName($bank_type)
    {
        foreach (self::getBankType() as $value) {
            if ($value['id'] == $bank_type) {
                return $value['name'];
            }
        }
        return '';
    }

    public static function getLicenseAddressProvinceName($license_address_province)
    {
        if ($license_address_province) {
            return Address::where('id', $license_address_province)->value('areaname');
        }
        return '';
    }

    public static function getLicenseAddressCityName($license_address_city)
    {
        if ($license_address_city) {
            return Address::where('id', $license_address_city)->value('areaname');
        }
        return '';
    }

    public static function getLicenseAddressDistrictName($license_address_district)
    {
        if ($license_address_district) {
            return Address::where('id', $license_address_district)->value('areaname');
        }
        return '';
    }

    public static function getLicenseType(): array
    {
        return [
            ['id' => 1,'name' => '个体工商户'],
            ['id' => 2,'name' => '有限责任公司'],
            ['id' => 3,'name' => '其他'],
        ];
    }

    public static function getValidType(): array
    {
        return [
            ['id' => 0,'name' => '非长期有效'],
            ['id' => 1,'name' => '长期有效'],
        ];
    }

    public static function getBankType(): array
    {
        return [
            ['id' => 1,'name' => '对私'],
            ['id' => 2,'name' => '对公'],
        ];
    }

    //此方法是获取地址，有用别修改
    public function getFullAddressAttribute()
    {
        $areaList = Address::whereIn('id', [$this->province_id, $this->city_id, $this->district_id])->pluck('areaname');
        $street_name = Street::select('areaname')->where('id',$this->street_id)->value('areaname');
        $areaList->push($street_name);
        return $areaList->push($this->address)->implode(' ');
    }

    /**
     * @name 获取供应商列表
     * @author yangyang
     * @param null $params
     * @param null $status
     * @return mixed
     */
    public static function getSupplierList($params = null, $status = null)
    {
        $list = Supplier::builder()->search($params)->orderBy('id', 'desc');
        return $list;
    }

    /**
     * @name 通过供应商id获取供应商信息
     * @author yangyang
     * @param $supplier_id
     * @param null $status
     * @return mixed
     */
    public static function getSupplierById($supplier_id, $status = null)
    {
        $supplier = Supplier::builder()->supplierId($supplier_id)->status($status)->first();
        return $supplier;
    }

    /**
     * @name 通过会员id获取供应商信息
     * @author yangyang
     * @param $member_id
     * @return mixed
     */
    public static function getSupplierByMemberId($member_id, $status = null)
    {
        $supplier = Supplier::builder()->memberId($member_id)->status($status)->first();
        return $supplier;
    }

    public static function getSupplierByUid($uid)
    {
        return self::builder()->byUid($uid);
    }

    public function scopeByUid($query, $uid)
    {
        return $query->where('uid', $uid);
    }

    /**
     * @name 通过账号获取供应商信息
     * @author yangyang
     * @param $username
     * @return mixed
     */
    public static function getSupplierByUsername($username)
    {
        $supplier = Supplier::builder()->uniacid()->username($username)->status(1)->first();
        return $supplier;
    }

    public static function getSupplierListByMemberIds($member_ids)
    {
        return Supplier::builder()->status(1)->uniacid()->whereIn('member_id', $member_ids->toArray())->orderBy('id', 'desc')->get();
    }

    /**
     * @name 构造器
     * @author yangyang
     * @param null $params
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public static function builder()
    {
        $columns = \Schema::getColumnListing('yz_supplier');
        unset($columns['password']);
        unset($columns['username']);
        unset($columns['ali_username']);
        unset($columns['ali']);
        unset($columns['bank_of_accounts']);
        unset($columns['bank_username']);
        unset($columns['certificate']);
        unset($columns['company_ali']);
        unset($columns['company_ali_username']);
        unset($columns['company_bank']);
        $builder = Supplier::select($columns)->with(
            [
                'hasOneMember',
                'hasOneWqUser' => self::wqUserBuilder(),
                'hasOneGroup',
                'hasOneCategory'
            ]
        );
        if (app('plugins')->isEnabled('package-deliver')) {
            $builder->with(['manyDeliver']);
        }
        return $builder;
    }

    public function hasOneAptitude()
    {
        return $this->hasOne(SupplierAptitude::class, 'supplier_id', 'id');
    }

    public function hasOneCategory()
    {
        return $this->hasOne(SupplierCategory::class, 'id', 'category_id');
    }

    public function hasOneGroup()
    {
        return $this->hasOne(SupplierGroup::class, 'id', 'group_id');
    }


    public function manyDeliver()
    {
        return $this->belongsToMany(Deliver::class, SupplierRelevancePackageDeliver::class, 'supplier_id', 'package_deliver_id')->whereNull('yz_supplier_relevance_package_deliver.deleted_at');
    }
    /**
     * @name 关联会员表
     * @author yangyang
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOneMember()
    {
        return $this->hasOne('app\common\models\Member', 'uid', 'member_id');
    }
    /**
     * @name 关联会员表
     * @author yangyang
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hasManyGoods()
    {
        return $this->hasMany(SupplierGoods::class, 'supplier_id', 'id');
    }

    public function hasOneWqUser()
    {
        return $this->hasOne(WeiQingUsers::class, 'uid', 'uid');
    }

    private static function wqUserBuilder()
    {
        return function ($query) {
            return $query->select('uid', 'username');
        };
    }

    /**
     * @name 供应商id查询
     * @author yangyang
     * @param $query
     * @param $supplier_id
     * @return mixed
     */
    public function scopeSupplierId($query, $supplier_id)
    {
        return $query->where('id', $supplier_id);
    }

    /**
     * @name 会员id查询
     * @author yangyang
     * @param $query
     * @param $member_id
     * @return mixed
     */
    public function scopeMemberId($query, $member_id)
    {
        return $query->where('member_id', $member_id);
    }

    /**
     * @name 账号查询
     * @author yangyang
     * @param $query
     * @param $username
     * @return mixed
     */
    public function scopeUsername($query, $username)
    {
        return $query->where('username', $username);
    }

    /**
     * @name 状态查询
     * @author yangyang
     * @param $query
     * @param null $status
     * @return mixed
     */
    public function scopeStatus($query, $status = null)
    {
        if (isset($status)) {
            return $query->where('status', $status);
        }
        return $query;
    }


    protected function getWhere($status)
    {
        if($status == 1){
            return ['status'=>0];
        }elseif($status == 2){
            return ['status'=>1];
        }elseif($status == 3){
            return ['status'=>2];
        }elseif($status == 4){
            return ['status'=>-1];
        }

    }

    /**
     * @name 检索条件
     * @author yangyang
     * @param $query
     * @param $params
     * @return mixed
     */
    public function scopeSearch($query, $params)
    {

        $query->uniacid();
        if (!$params) {
            return $query;
        }
        if ($params['member_id']) {
            $query->where('member_id', 'like', '%' . $params['member_id'] . '%');
        }
        if ($params['supplier']) {
            $query->where('username', 'like', '%' . $params['supplier'] . '%');
        }

        if ($params['company_name']) {
            $query->where('company_name', 'like', '%' . $params['company_name'] . '%');
        }
        if($params['status']){
            $where = $this->getWhere($params['status']);
            $query->where($where);
        }
        if ($params['store_name']) {
            $query->where('store_name', 'like', '%' . $params['store_name'] . '%');
        }
        if ($params['supplier_id']) {
            $query->where('id', $params['supplier_id']);
        }
        if($params['role_id']) {
            $query->where('role_id', $params['role_id']);
        }

        if ($params['group_id']) {
            $query->where('group_id', $params['group_id']);
        }
        if ($params['category_id']) {
            $query->where('category_id', $params['category_id']);
        }
        if ($params['member']) {
            $query->whereHas('hasOneMember', function ($member) use ($params) {
                return $member->searchLike($params['member']);
            });

            $query->orWhere('mobile', 'like', '%' . $params['member'] . '%');
        }
        if ($params['settled_status']) {
            $query->bySettledStatus($params['settled_status']);
//            $time =  strtotime("-1 year", time());
//            if ($params['settled_status'] == '-1') {
//                $query->whereRaw('created_at + settlement_time < '. $time);
//            } elseif ($params['settled_status'] == '2') {
//                $month_time = $time + 2592000;
//                $query->whereRaw('created_at + settlement_time between '. $time.' and '.$month_time);
//            } else {
//                $query->whereRaw('created_at + settlement_time >'. $time);
//            }
        }
        return $query;
    }

    /**
     * 定义字段名
     *
     * @return array */
    public  function atributeNames() {
        return [
            'username'  => '用户名',
          //  'member_id'  => '微信号',
            'password'  => '密码',
            'mobile' => '手机号码'
        ];
    }

    /**
     * 字段规则
     *
     * @return array */
    public  function rules()
    {
        $passwordRule = '';
        if (!$this->id) {
            $passwordRule = 'required';
        }

        $set = \Setting::get('plugin.supplier');
        $rule = [
            'username'  => [
//                'alpha_num',
                Rule::unique($this->table)->where('uniacid', \YunShop::app()->uniacid)->where('status',1)->ignore($this->id)],
          /*  'member_id'  => [
                'required',
                'integer',
                'min:1',
                Rule::unique($this->table)->where('status',1)->ignore($this->id)
            ],
            'password'  => $passwordRule,*/
        ];

//        if (!$set['apply_info_set'] || $set['apply_info_set'] != '1') {
//            $rule['mobile'] = [
//                'required',
//                'regex:/^1\d{10}$/',
//                Rule::unique($this->table)->where('status',1)->ignore($this->id)
//            ];
//        }
        return $rule;
    }

    public function validationMessages()
    {
        return array_merge(parent::validationMessages(),[
            'alpha'=>'用户名必须是中文、数字、字母',
        ]);
    }

    /**
     * @name 供应商密码加密
     * @author yangyang
     * @param $password
     * @param $salt
     * @return string
     */
    public static function user_hash($password, $salt)
    {
        //$config = \YunShop::app()['config']['setting']['authkey'];
        //$password = "{$password}-{$salt}-{$config}";
        $password = "{$password}-{$salt}";
        return sha1($password);
    }

    // public static function boot()
    // {
    //     parent::boot();

    //     static::observe(new SupplierObserver());
    //     static::addGlobalScope(function (Builder $builder) {
    //         $builder->uniacid();
    //     });
    // }

    public static function addWeiqingTables($username, $password)
    {
        $uid = user_register(array('username' => $username, 'password' => $password), '');
        if (is_array($uid) || $uid == 0) {
            return $uid;
        }

        UniAccountUser::AddUniAccountUser($uid);

        WeiQingUsers::updateType($uid);

        (new UsersPermission())->addUsersPermission($uid);
        return $uid;
    }

    public function ScopeGetSupplierInfoById($q, $sid)
    {
        $supplier = self::supplierId($sid)->select('mobile','store_name','certificate','address','province_id','city_id','district_id','street_id','address','created_at','username','uid')->first();
        $areaList = Address::whereIn('id', [$supplier->province_id, $supplier->city_id, $supplier->district_id, $supplier->street_id])->pluck('areaname');
        $supplier['address'] = $areaList->implode('-');
        return $supplier;
    }

    public function ScopeGetAverageScore($q, $goods_ids)
    {
        $score_total = self::commentBuilder($goods_ids)->sum('level');
        $comment_total = self::commentBuilder($goods_ids)->count();
        $average_score = $comment_total == 0 ? 5 : floor($score_total / $comment_total * 10) / 10;

        return $average_score;
    }

    public function commentBuilder($goods_ids)
    {
        return Comment::select(
            'id', 'order_id', 'goods_id', 'uid', 'nick_name', 'head_img_url', 'content', 'level',
            'images', 'created_at', 'type')
            ->uniacid()
            ->with(['hasManyReply' => function ($query) {
                return $query->where('type', 2)
                    ->orderBy('created_at', 'asc');
            }])
            ->whereIn('goods_id', $goods_ids)
            ->where('comment_id', 0);
    }

    public function scopeProfileWith(Builder $builder)
    {
        return $builder->with([
            'hasOneProfile:uid,mobile'
        ]);
    }

    // 关联公众号粉丝
    public function hasOneFans()
    {
        return $this->hasOne(McMappingFans::class, 'uid', 'member_id');
    }

    // 关联小程序粉丝
    public function hasOneMiniApp()
    {
        return $this->hasOne(MemberMiniAppModel::class, 'member_id', 'member_id');
    }

    // 关联开放平台粉丝
    public function hasOneUnique()
    {
        return $this->hasOne(MemberUnique::class, 'member_id', 'member_id');
    }

    /**
     * 获取与用户表相关的用户信息
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOneProfile()
    {
        return $this->hasOne(\app\platform\modules\user\models\YzUserProfile::class, 'uid', 'uid');
    }

    public static function subPlatformOpen()
    {
        //子平台插件开启可不填账号密码
        return app('plugins')->isEnabled('sub-platform') && \Yunshop\SubPlatform\services\SettingService::applySwitch();
    }


    /**
     * 入驻状态
     * @param $query
     * @param $settled_status -1过期，1-正常，2-即将过期
     * @return mixed
     */
    public function scopeBySettledStatus($query, $settled_status)
    {
        $time = strtotime("-1 year", time());
        if ($settled_status == '-1') {
            $query->whereRaw('created_at + settlement_time < '. $time);
        } elseif ($settled_status == '2') {
            $month_time = $time + 2592000;
            $query->whereRaw('created_at + settlement_time between '. $time.' and '.$month_time);
        } else {
            $query->whereRaw('created_at + settlement_time >'. $time);
        }

        return $query;
    }


}
