<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\frontend\modules\project\services\DownloadLimitService;
use Illuminate\Http\Request;

/**
 * 下载限流控制器
 *
 * 只负责检测当前 IP 是否允许下载，不处理实际文件下载逻辑。
 * 前端根据返回结果决定是否触发下载行为。
 *
 * 支持的下载按钮类型（file_type）：
 *   3d         - 3D 模型下载（每分钟 5 次 / 每小时 20 次）
 *   cad        - CAD 文件下载（每分钟 5 次 / 每小时 20 次）
 *   atlas      - 图册 PDF 下载（每分钟 10 次 / 每小时 50 次）
 *   color_card - 色卡下载（每分钟 10 次 / 每小时 50 次）
 */
class DownloadController extends ApiController
{
    /**
     * 下载前置检测
     *
     * 请求参数：
     *   goods_id  int    必填，商品 ID
     *   file_type string 必填，下载按钮类型: 3d | cad | atlas | color_card
     *
     * 返回结果：
     *   通过 -> result=0, msg='ok',        data.allow=true
     *   拒绝 -> result=0, msg='频繁提示', data.allow=false, data.retry_after=秒数
     *
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        $this->validate([
            'goods_id'  => 'required|integer|min:1',
            'file_type' => 'required|string|in:3d,cad,atlas,color_card',
        ]);

        $ip       = $request->ip();
        $uid      = (int) (\YunShop::app()->getMemberId() ?? 0);
        $goodsId  = (int) $request->input('goods_id');
        $fileType = $request->input('file_type');

        $service = new DownloadLimitService();
        $result  = $service->check($ip, $goodsId, $fileType, $uid);

        if (!$result['allow']) {
            return $this->successJson($result['reason'], [
                'allow'       => false,
                'retry_after' => $result['retry_after'],
            ]);
        }

        return $this->successJson('ok', [
            'allow'       => true,
            'retry_after' => 0,
        ]);
    }
}
