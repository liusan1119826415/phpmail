<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\frontend\models\Member;
use Illuminate\Support\Facades\Cache;
use app\frontend\modules\member\models\MemberUniqueModel;
use app\frontend\modules\member\models\MemberWechatQrcodeModel;
use app\frontend\modules\project\services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Yunshop\Supplier\common\models\AccountVerify;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\UpdateApplyLog;
use Illuminate\Support\Carbon;

class MemberController extends ApiController
{

    public $transactionActions = ["applySupplier", "improveShop"];

    protected $publicAction = ["handleCallback", "generateQrCode"];
    protected $ignoreAction = ["handleCallback", "generateQrCode"];
    /**
     *创建我的项目
     */

    private MemberService $memberService;

    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
        parent::__construct();
    }


    public function storeUserProfile(Request $request)
    {

        $validat = [
            'nickname' => 'required|string',
            'avatar' => 'required|string',
            'industry_id' => 'required',
            'identity_id' => 'required',
            'province_id' => 'required',
            'city_id' => 'required',
        ];
        $this->validate($validat, $request, [
            'realname.required' => '请输入账户/企业名称',
            'avatar.required' => '请上传头像',
            'industry_id.required' => '请选择行业',
            'identity_id.required' => '请选择身份标识',
            'province_id.required' => '请选择省',
            'city_id.required' => '请选择市',

        ]);
        $validated = $request->validate($validat);
        $this->PreventDuplicateSubmission($request);
        $this->memberService->storeUserProfile($validated);
        return $this->successJson('ok');
    }


    public function getIndustry(Request $request)
    {
        $id = $request->input('id', 0);

        $data = $this->memberService->getIndustry($id);
        return $this->successJson('ok', $data);
    }


    public function getMemberInfo(Request $request)
    {


        $data = $this->memberService->getMemberInfo();
        return $this->successJson('ok', $data);
    }

    public function updatePwd(Request $request)
    {

        $validat = [
            'or_password' => 'required|string',
            'password' => 'required|string|min:6|max:20',
            're_password' => 'required|string|same:password',
        ];

        $this->validate($validat, $request, [

            'or_password.required' => '请输入原始密码',
            'password.required' => '请输入新密码',
            'password.min' => '密码长度不能少于6位',
            'password.max' => '密码长度不能超过20位',
            're_password.required' => '请再次输入新密码',
            're_password.same' => '两次输入的新密码不一致',
        ]);
        $this->memberService->updatePwd();
        return $this->successJson('ok');
    }

    public function verifyRealName(Request $request)
    {
        $rules = [
            'real_name' => 'required|string|max:50',
            'id_type' => 'required|integer',
            'id_number' => 'required|string|max:30',
            //'id_photo_handheld' => 'required|string',
            'id_photo_front' => 'required|string',
            'id_photo_back' => 'required|string',
            'mobile' => 'required|string|regex:/^1[3-9][0-9]{9}$/',
            'code' => 'required|string',
        ];

        $messages = [
            'real_name.required' => '请输入真实姓名',
            'id_type.required' => '请选择证件类型',
            'id_number.required' => '请输入证件号码',
            //  'id_photo_handheld.required' => '请上传手持身份证照片',
            'id_photo_front.required' => '请上传身份证正面照片',
            'id_photo_back.required' => '请上传身份证反面照片',
            'mobile.required' => '请输入手机号',
            'mobile.regex' => '手机号格式不正确',
            'code.required' => '请输入短信验证码',
        ];

        $this->validate($rules, $request, $messages);
        $data = $request->validate($rules);
        $this->memberService->verifyRealName($data);
        return $this->successJson('ok');
    }


    public function verifyCompany(Request $request)
    {
        $rules = [
            'company_name' => 'required|string',
            'credit_code' => 'required|string',
            'legal_person' => 'required|string',
            'license_image' => 'required|string',
            'tax_number' => 'required|string',
            'bank_name' => 'required|string',
            'bank_account' => 'required|string',
            'company_address' => 'required|string',
            'company_phone' => 'required|string',
            'mobile' => 'required|string|regex:/^1[3-9][0-9]{9}$/',
            'code' => 'required|string',
        ];

        $messages = [
            'company_name.required' => '请输入企业名称',
            'credit_code.required' => '请输入统一社会信用代码',
            'legal_person.required' => '请输入企业法定代表人',
            'license_image.required' => '请上传营业执照',
            'tax_number.required' => '请输入纳税人识别号',
            'bank_name.required' => '请输入开户银行',
            'bank_account.required' => '请输入银行账号',
            'company_address.required' => '请输入企业地址',
            'company_phone.required' => '请输入企业电话',
            'mobile.required' => '请输入手机号',
            'mobile.regex' => '手机号格式不正确',
            'code.required' => '请输入短信验证码',
        ];

        $this->validate($rules, $request, $messages);
        $data = $request->validate($rules);
        $this->memberService->verifyCompany($data);
        return $this->successJson('ok');
    }


    public function getRealNameStatus()
    {
        return $this->successJson('ok', $this->memberService->getRealNameStatus());
    }

    public function getCompanyStatus()
    {
        return $this->successJson('ok', $this->memberService->getCompanyStatus());
    }


    public function applyStepOne(Request $request)
    {

        $validat = [

            'company_name' => 'required|string', //公司名称
            'credit_code' => 'required|string', //统一社会信用码
            'province_id' => 'required|integer',
            'city_id' => 'required|integer',
            'district_id' => 'required|integer',
            'address' => 'required|string', //详细地址
            'certificate' => 'required|string',//营业执照
            'id_card_front' => 'required|string', //身份证正面
            'id_card_back' => 'required|string', //身份证反面
            'legal_person' => 'required|string', //法定代表人姓名
            'id_card_no' => 'required|string',//法定代表人身份证号码
            'mobile' => 'required|string', //法定代表人手机号码
            // 'code'=>'required|string', //验证码

        ];

        $this->validate($validat, $request, [
            'company_name.required' => '请输入公司名称',
            'credit_code.required' => '请输入统一社会信用码',
            'province_id.required' => '请选择省',
            'city_id.required' => '请选择市',
            'district_id.required' => '请选择区',
            'credit_code.required' => '请输入统一社会信用代码',
            'address.required' => '请输入详细地址',
            'province_id.required' => '请选择省份',
            'city_id.required' => '请选择城市',
            'district_id.required' => '请选择区县',

            'address.required' => '请输入详细地址',
            'certificate.required' => '请上传营业执照',
            'id_card_front.required' => '请上传身份证正面',
            'id_card_back.required' => '请上传身份证反面',
            'legal_person.required' => '请输入法定代表人姓名',
            'id_card_no.required' => '请输入法定代表人身份证号码',
            'mobile.required' => '请输入法定代表人手机号码',
            //'code.required' => '请输入验证码',
        ]);

        $validated = $request->validate($validat);

        $data = $this->memberService->applyStepOne($validated);
        return $this->successJson('ok', $data);

    }

    //供应商申请第二步
    public function applyStepTwo(Request $request)
    {

        $validat = [

            'username' => 'required|string', //商家登录账户
            'store_name' => 'required|string', //店铺名称
            /*'logo' => 'required|string', //店铺LOGO
            'delivery_address.province_id' => 'required|integer',
            'delivery_address.city_id' => 'required|integer',
            'delivery_address.district_id' => 'required|integer',
            'delivery_address.address' => 'required|string',*/

        ];

        $this->validate($validat, $request, [
            'username.required' => '请输入商家登录账户',
            'store_name.required' => '请输入店铺名称',
            /* 'logo.required' => '请上传店铺LOGO',
             'delivery_address.province_id.required' => '请选择提货地址省',
             'delivery_address.city_id.required' => '请选择提货地址市',
             'delivery_address.district_id.required' => '请选择提货地址区',
             'delivery_address.address.required' => '请输入提货详细地址',*/
        ]);

        $validated = $request->validate($validat);

        $data = $this->memberService->applyStepTwo($validated);
        return $this->successJson('ok', $data);

    }

    //供应商申请第三步
    public function applyStepThree(Request $request)
    {
        $validat = [

            'apply_name' => 'required|string', //管理人姓名
            'apply_phone' => 'required|string', //管理人手机
            'apply_email' => 'required|string', //管理人邮箱

        ];

        $this->validate($validat, $request, [
            'apply_name.required' => '请输入管理人姓名',
            'apply_phone.required' => '请输入管理人手机',
            'apply_email.required' => '请输入管理人邮箱',

        ]);
        $validated = $request->validate($validat);

        $data = $this->memberService->applyStepThree($validated);
        return $this->successJson('ok', $data);
    }


    //供应商申请对公账户验证
    public function applyAccountVerify(Request $request)
    {
        $validat = [

            'bank_account' => 'required|string', //银行卡号
            'bank_username' => 'required|string', //开户人姓名
            'bank_of_accounts' => 'required|string', //开户银行
            'opening_branch' => 'required|string', //联行号

        ];

        $this->validate($validat, $request, [
            'bank_account.required' => '请输入银行卡号',
            'bank_username.required' => '请输入开户人姓名',
            'bank_of_accounts.required' => '请输入开户银行',
            'opening_branch.required' => '请输入联行号',

        ]);
        $validated = $request->validate($validat);

        $data = $this->memberService->applyAccountVerify($validated);
        return $this->successJson('ok', $data);
    }


    public function sendCode(Request $request)
    {
        $mobile = \YunShop::request()->mobile;
        $state = \YunShop::request()->state ?: '86';
        if (empty($mobile)) {
            return $this->errorJson('请填入手机号');
        }
        try {
            \app\frontend\modules\member\services\MemberService::mobileValidate([
                'mobile' => $mobile,
                'state' => $state,
            ]);
        } catch (ShopException $exception) {
            return $this->errorJson($exception->getMessage());
        }
        $sms = app('sms')->sendLog($mobile);

        if (0 == $sms['status']) {
            return $this->errorJson($sms['json']);
        }

        return $this->successJson();

    }


    public function applySupplier(Request $request)
    {
        $validat = [
            'username' => 'required|string',
            'realname' => 'required|string',
            'mobile' => 'required|string',
            'store_name' => 'required|string',
            'company_name' => 'required|string',
            'credit_code' => 'required|string',
            'legal_person' => 'required|string',
            'province_id' => 'required|integer',
            'city_id' => 'required|integer',
            'district_id' => 'required|integer',
            'address' => 'required|string',
            'certificate' => 'required|string',
            'apply_name' => 'required|string',
            'apply_phone' => 'required|string',
            'apply_email' => 'required|string',
            'id_card_front' => 'required|string',
            'id_card_back' => 'required|string',
            'id_card_no' => 'required|string',
        ];

        $this->validate($validat, $request, [
            'username.required' => '请输入账户名',
            'realname.required' => '请输入负责人姓名',
            'mobile.required' => '请输入手机号码',
            'store_name.required' => '请输入店铺名称',
            'company_name.required' => '请输入企业全称',
            'credit_code.required' => '请输入统一社会信用代码',
            'legal_person.required' => '请输入法定代表人',
            'province_id.required' => '请选择省份',
            'city_id.required' => '请选择城市',
            'district_id.required' => '请选择区县',

            'address.required' => '请输入详细地址',
            'certificate.required' => '请上传营业执照',
            'apply_name.required' => '请输入申请人姓名',
            'apply_phone.required' => '请输入申请人手机',
            'apply_email.required' => '请输入申请人邮箱',
        ]);

        $validated = $request->validate($validat);

        $data = $this->memberService->applySupplier($validated);
        return $this->successJson('ok', $data);

    }

    public function getApplyStatus(Request $request)
    {
        $data = $this->memberService->getApplyStatus();
        return $this->successJson('ok', $data);
    }


    public function improveShop(Request $request)
    {
        $validat = [
            /*            'bank_account' => 'required|string',
                        'bank_username' => 'required|string',
                        'bank_of_accounts' => 'required|string',
                        'store_name' => 'required|string',
                        'opening_branch' => 'required|string',
                        'company_ali_username' => 'required|string',
                        // delivery_address 验证
                        'delivery_address.province_id' => 'required|integer',
                        'delivery_address.city_id' => 'required|integer',
                        'delivery_address.district_id' => 'required|integer',
                        'delivery_address.address' => 'required|string',*/
            'password' => 'required|string|min:6|max:20',
            're_password' => 'required|string|same:password',


        ];

        $this->validate($validat, $request, [
            /*  'bank_account.required' => '请输入银行账号',
              'bank_username.required' => '请输入开户人姓名',
              'bank_of_accounts.required' => '请输入开户行',
              'opening_branch.required' => '请输入行号',
              'company_ali_username.required' => '请输入企业支付宝用户名',
              'delivery_address.province_id.required' => '请选择提货地址省',
              'delivery_address.city_id.required' => '请选择提货地址市',
              'delivery_address.district_id.required' => '请选择提货地址区',
              'delivery_address.address.required' => '请输入提货详细地址',*/
            'password.required' => '请输入密码',
            're_password.required' => '请再次输入密码',
            're_password.same' => '两次输入的密码不一致',
            'password.min' => '密码长度不能少于6位',
            'password.max' => '密码长度不能超过20位',

        ]);

        $validated = $request->validate($validat);

        $data = $this->memberService->improveShop($validated);
        return $this->successJson('ok', $data);

    }

    public function amountVerify(Request $request)
    {
        $validat = [
            'amount' => 'required',

        ];

        $this->validate($validat, $request, [
            'amount.required' => '请输入验证金额',

        ]);

        $validated = $request->validate($validat);
        $data = $this->memberService->amountVerify($validated);
        return $this->successJson('ok');

    }

    public function updateContacts(Request $request)
    {
        $validat = [
            'apply_name' => 'required|string',
            'apply_phone' => 'required|string',
            'apply_email' => 'required|string',
        ];

        $this->validate($validat, $request, [
            'apply_name.required' => '请输入申请人姓名',
            'apply_phone.required' => '请输入申请人手机',
            'apply_email.required' => '请输入申请人邮箱',
        ]);

        $validated = $request->validate($validat);
        $supplier = Supplier::where('member_id', \YunShop::app()->getMemberId())->where('status', '!=', -1)->first();
        $memberId = \YunShop::app()->getMemberId();
        UpdateApplyLog::createUpdateLogs([
            "member_id" => $memberId,
            "supplier_id" => $supplier->id,
            "name" => $validated['apply_name'],
            'mobile' => $validated['apply_phone'],
            'email' => $validated['apply_email'],
            'old_name' => $supplier->apply_name,
            'old_mobile' => $supplier->apply_phone,
            'old_email' => $supplier->apply_email,

        ]);
        Supplier::where('member_id', \YunShop::app()->getMemberId())->where('status', '!=', -1)->update(
            $validated
        );

        return $this->successJson('ok');
    }


    public function getFollowSupplier(Request $request)
    {


        $name = $request->input('name', "");
        $data = $this->memberService->getFollowSupplier($name);
        return $this->successJson('ok', $data);
    }

    public function getFollowCase(Request $request)
    {
        $search = $request->input('search', []);
        $data = $this->memberService->getFollowCase($search);
        return $this->successJson('ok', $data);
    }

    public function getFollowCaseLable(Request $request)
    {
        $data = $this->memberService->getFollowCaseLable();
        return $this->successJson('ok', $data);
    }

    public function cancelApply(Request $request)
    {
        $supplier_id = $request->input('id');
        Supplier::where('id', $supplier_id)->delete();
        return $this->successJson('ok');
    }

    public function revalidation(Request $request)
    {
        $hour24 = 86400;
        $supplier_id = $request->input('id');
        $member_id = \YunShop::app()->getMemberId();
        $supplier = Supplier::find($supplier_id);
        if ($supplier->verify_num >= 2) {
            $account_verify = AccountVerify::where('member_id', $member_id)
                ->where('supplier_id', $supplier_id)
                ->whereIn('status', [1, 3])
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$account_verify) {
                throw new AppException("验证记录不存在");
            }

            $now = Carbon::now();
            $secondsPassed = $now->diffInSeconds($account_verify->created_at);

            if ($secondsPassed < $hour24) {
                $remainingSeconds = $hour24 - $secondsPassed;

                // Convert seconds to hours, minutes, seconds
                $hours = floor($remainingSeconds / 3600);
                $minutes = floor(($remainingSeconds % 3600) / 60);
                $seconds = $remainingSeconds % 60;

                throw new AppException(sprintf("还剩 %d小时 %d分钟 %d秒 解除", $hours, $minutes, $seconds));
            }


        }

        Supplier::where('id', $supplier_id)->update([
            'pay_status' => 0,
            'sub_status' => 1
        ]);


        return $this->successJson('ok');
    }

    public function checkUser(Request $request)
    {
        $username = $request->input('username');
        $data = \app\common\models\user\WeiQingUsers::where('username', $username)->first();
        if ($data) {
            return $this->errorJson("账号已经存在");
        }
        return $this->successJson('ok');
    }

    public function generateQrCode()
    {
        $data = $this->memberService->generateQrCode();
        return $this->successJson('ok', $data);
    }

    public function handleCallback()
    {
         $data = $this->memberService->handleCallback();

        $success_js = $data['success'] ? 'true' : 'false';
        $message_js = addslashes($data['message']);
        $parent_domain = 'https://'.$_SERVER['HTTP_HOST']; // 在这里定义域名

        echo <<<HTML
<!doctype html>
<meta charset="utf-8">
<script>
    (function() {
        var messageData = {
            type: 'WECHAT_BIND_DONE',
            payload: {
                success: $success_js,
                message: '$message_js'
            }
        };

        try {
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage(messageData, '$parent_domain');
            }
        } catch (e) {}

        setTimeout(function() { window.close(); }, 300);
    })();
</script>
HTML;

        die;
    }


    public function unbind()
    {


        $memberId = \YunShop::app()->getMemberId();
        $member = Member::where('uid',$memberId)->first();

        if(!$member->mobile){
            throw new AppException('请绑定手机号');
        }
        $MemberUniqueModel = MemberUniqueModel::where('member_id', $memberId)->first();
        if (!$MemberUniqueModel) {
            throw new AppException('账号未绑定微信');
        }
        $MemberUniqueModel->delete();
        $member->nickname = $member->mobile;
        $member->avatar = '/static/images/photo-mr.jpg';
        $member->save();
        MemberWechatQrcodeModel::where('member_id', $memberId)->delete();
        return $this->successJson('ok');
    }

    public function bindStatus(Request $request)
    {
        $state = $request->input('state');

        if (!$state) {
            throw new AppException('缺少参数');
        }

        $result = Cache::get("wechat_bind_result:$state");

        if (!$result) {
            return response()->json(['status' => 'pending']); // 仍在等待扫码或授权中
        }

        return response()->json($result); // { status: success | fail, message: "" }
    }

    public function getNotifice(Request $request)
    {
        $search = $request->input('search', []);

        $data = $this->memberService->getNotifice($search);
        return $this->successJson('ok', $data);
    }


    public function setRead(Request $request)
    {
        $params = $request->input('params', []);

        $data = $this->memberService->setRead($params);
        return $this->successJson('ok', $data);
    }


    public function getUnRead(Request $request)
    {

        $data = $this->memberService->getUnRead();
        return $this->successJson('ok', $data);
    }

    public function checkAuthStatus(Request $request)
    {

        $state = $request->input('state');
        $cacheKey = 'wechat_auth_state:' . $state;

        if (!Cache::has($cacheKey)) {
            return $this->errorJson("授权已过期");
        }

        $authData = Cache::get($cacheKey);
        if($authData['status'] == "unbound"){
            return $this->errorJson("用户微信账户未绑定",$authData);
        }

        return $this->successJson('ok',$authData);
    }


}