<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

/**
 * 会员中心
 * Interface MemberRepositoryInterface
 * @package app\frontend\modules\project\repositories
 */
interface MemberRepositoryInterface
{



    public function storeUserProfile(array $data):bool;

    public function getIndustry(int $id):array;

    //获取个人信息
    public function getMemberInfo():array;

    //修改密码
    public function updatePwd():bool;

    //实名认证
    public function verifyRealName(array $data):bool;

    public function getRealNameStatus():array;

    //企业认证
    public function verifyCompany(array $data):bool;


    public function getCompanyStatus():array;

    public function applySupplier(array $data):array;

    public function getApplyStatus():array;

    public function improveShop(array $data):bool;

    public function getFollowSupplier($name):array;

    public function getFollowCase(array $search):array;

    public function getFollowCaseLable():array;

    public function applyStepOne(array $data):array;

    public function applyStepTwo(array $data):array;

    public function applyStepThree(array $data):array;
    public function amountVerify(array $data):bool;

    public function generateQrCode():array;

    public function handleCallback();

    public function getNotifice(array $search):array;

    public function setRead(array $search):array;

    public function getUnRead():array;


}