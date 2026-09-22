<?php


namespace app\common\services\goods;


use app\backend\modules\goods\models\GoodsStyleRelations;
use app\backend\modules\goods\models\GoodsVideo;
use app\backend\modules\goods\services\GoodsService;
use app\backend\modules\goods\services\SpecOptionService;

use Setting;
use app\common\models\goods\Supplier;
use app\common\models\goods\SupplierGoods;
use Illuminate\Support\Facades\DB;
class CreateGoodsService extends \app\backend\modules\goods\services\CreateGoodsService
{


    public function importGoods($goods_data)
    {
        // 开始数据库事务

        try {
            $this->goods_model = $this->getGoodsModel();

            if ($goods_data) {

                //正则匹配富文本更改图片标签
                if ($goods_data['content']) {
                    $goods_data['content'] = changeUmImgPath($goods_data['content']);
                }

                $goods_data['content'] = preg_replace('/class="[^=]*/', 'class="edui-upload-video" controls', htmlspecialchars_decode($goods_data['content']));

                preg_match('/<video[^>]*/', $goods_data['content'], $matches);

                $video_matche = '<video x5-playsinline="true" webkit-playsinline="true" playsinline="true" ';

                $goods_data['content'] = str_replace('<video', $video_matche, $goods_data['content']);
                $goods_data['content'] = htmlspecialchars($goods_data['content']);

                if ($this->type == 1) {
                    $goods_data['status'] = 0;
                }

                if (!$goods_data['virtual_sales']) {
                    $goods_data['virtual_sales'] = 0;
                }

                if (empty($goods_data['price'])) {
                    $goods_data['price'] = $this->getPrice($goods_data['goods_option']['option']);
                }
                if (empty($goods_data['cost_price'])) {
                    $goods_data['cost_price'] = 0;
                }

                if ($goods_data['related_goods_id']) {
                    $goods_data['related_goods_id'] = implode(",", $goods_data['related_goods_id']);
                } else {
                    $goods_data['related_goods_id'] = "";
                }

                $goods_data['pdf_page_img'] = $goods_data['pdf_page_img'] ? serialize($goods_data['pdf_page_img']) : serialize([]);
                $goods_data['wiring_diagram'] = ($goods_data['wiring_diagram'] && $goods_data['wiring_diagram'] != "undefined") ? serialize($goods_data['wiring_diagram']) : serialize([]);
                $goods_data['thumb_url'] = ($goods_data['thumb_url'] && $goods_data['thumb_url'] != "undefined") ? serialize($goods_data['thumb_url']) : serialize([]);
                $goods_data['real_image'] = ($goods_data['real_image'] && $goods_data['real_image'] != "undefined") ? serialize($goods_data['real_image']) : serialize([]);
                $goods_data['main_url'] = $goods_data['main_url'] ? serialize($goods_data['main_url']) : serialize([]);

                $goods_data['atlas'] = $goods_data['atlas']?json_encode($goods_data['atlas']):json_encode([]);
                $goods_data['maintenance_doc'] = $goods_data['maintenance_doc']?serialize($goods_data['maintenance_doc']):serialize([]);
                $goods_data['install_guide'] = $goods_data['install_guide']?serialize($goods_data['install_guide']):serialize([]);

                if ($goods_data['structure'] == "undefined" || $goods_data['structure'] == "") {
                    $goods_data['structure'] = "";
                }

                if ($goods_data['design'] == "undefined" || $goods_data['design'] == "") {
                    $goods_data['design'] = "";
                }

                $goods_data['has_option'] = 1;

                $save_data = array_except($goods_data, ['category', 'withhold_stock', 'video_image', 'goods_video', 'category_to_option', 'craft_materials', 'goods_style']);

                $this->goods_model->fill($save_data);

                $this->setAfterHandle();
                $this->goods_model->uniacid = $goods_data['uniacid'];
                $this->goods_model->weight = $this->goods_model->weight ? $this->goods_model->weight : 0;
                $this->goods_model->supp_id = $goods_data['supplier_id'];

                $validator = $this->goods_model->validator($this->goods_model->getAttributes());
                if ($validator->fails()) {

                    return ['status' => -1, 'msg' => $validator->messages()];
                } else {
                    if ($this->goods_model->save()) {

                        \Log::debug("====导入goods_id====", [$this->goods_model->id]);

                        // 商品操作日志
                        (new \app\common\services\operation\GoodsLog($this->goods_model, 'create'));

                        // 商品视频保存
                        GoodsVideo::store($this->goods_model->id, array_only($goods_data, ['video_image', 'goods_video']));


                        // 商品工艺材质保存
                       GoodsStyleRelations::saveStyle($this->goods_model->id, $goods_data['craft_materials'], 2);


                        // 商品风格保存
                       GoodsStyleRelations::saveStyle($this->goods_model->id, $goods_data['goods_style'], 1);


                        // 商品分类保存
                        GoodsService::store($this->goods_model->id, $goods_data['category'], \Setting::get('shop.category')['cat_level'], $goods_data['category_to_option']);


                        // 规格项和规格组合保存
                        SpecOptionService::store($this->goods_model->id, $goods_data['goods_option'], $goods_data['uniacid']);


                        // 商品保存之后的其他操作
                        $this->afterSaving();

                        // 供应商商品关联
                        $supplier = Supplier::find($goods_data['supplier_id']);
                        if (!$supplier) {
                            throw new \Exception('供应商不存在');
                        }

                        if (!SupplierGoods::where('goods_id', $this->goods_model->id)->where('supplier_id', $goods_data['supplier_id'])->first()) {
                            $supplierGoods = SupplierGoods::create([
                                'goods_id'      => $this->goods_model->id,
                                'supplier_id'   => $goods_data['supplier_id'],
                                'member_id'     => $supplier->member_id,
                            ]);

                            if (!$supplierGoods) {
                                throw new \Exception('供应商商品关联保存失败');
                            }
                        }

                        // 所有操作成功，提交事务


                        return ['status' => 1, 'goods_id' => $this->goods_model->id];
                    } else {

                        return ['status' => -1, 'msg' => '商品保存失败'];
                    }
                }
            }


            return ['status' => -1, 'msg' => '商品数据为空'];

        } catch (\Exception $e) {
            // 捕获异常，回滚事务


            \Log::error('商品导入失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'goods_data' => $goods_data
            ]);

            return ['status' => -1, 'msg' => '商品导入失败: ' . $e->getMessage()];
        }
    }


    private function getPrice($options)
    {
        $minPrice = 0;
        // 遍历数组查找最低价格
        foreach ($options as $option) {
            foreach ($option['modelTypes'] as $modelType) {
                if (isset($modelType['option']['product_price'])) {
                    $price = $modelType['option']['product_price'];

                    // 如果当前最低价格为 null 或者找到更小的价格，则更新最低价格
                    if ($minPrice === 0 || $price < $minPrice) {
                        $minPrice = $price;
                    }
                }
            }
        }

        return $minPrice;
    }






}