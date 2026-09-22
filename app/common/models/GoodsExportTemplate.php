<?php

namespace app\common\models;

use app\common\exceptions\AppException;

/**
 * Class GoodsExportTemplate
 * @package app\common\models
 * @property int id
 * @property int uniacid
 * @property string name
 * @property string checked_column
 * @property int plugin_id
 * @property int relation_id
 */
class GoodsExportTemplate extends BaseModel
{
    public $table = 'yz_goods_export_template';

    public $timestamps = true;

    protected $guarded = [''];

//    protected $fillable = [''];

    /**
     * 定义字段名
     * @return array
     */
    public function atributeNames()
    {
        return [
            'name' => '模板名称',
//            'checked_column' => '价格',
        ];
    }

    /**
     * 字段规则
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required',
//            'checked_column' => '价格',
        ];
    }

    public static function templateDetail($id,$plugin_id = null,$relation_id = null): GoodsExportTemplate
    {
        $model = GoodsExportTemplate::uniacid();
        if (!is_null($plugin_id)) {
            $model->where('plugin_id',$plugin_id);
        }
        if (!is_null($relation_id)) {
            $model->where('relation_id',$relation_id);
        }
        $model = $model->find($id);
        if (!$model) {
            throw new AppException('模板不存在!');
        }
        return $model;
    }

    /**
     * @param array $data
     * @return GoodsExportTemplate
     * @throws AppException
     */
    public static function createTemplate(array $data): GoodsExportTemplate
    {
        $model = new self();
        $model->fill([
            'uniacid' => \YunShop::app()->uniacid,
            'name' => $data['name'] ? : '',
            'checked_column' => $data['checked_column'] ? implode(',',$data['checked_column']) : '',
            'plugin_id' => $data['plugin_id'] ?: 0,
            'relation_id' => $data['relation_id'] ?: 0,
        ]);
        $validator = $model->validator();
        if ($validator->fails()) {
            throw new AppException($validator->messages()->first());
        }
        if (!$model->save()) {
            throw new AppException('保存失败');
        }
        return $model;
    }

    public static function editTemplate($id,array $data,$plugin_id = null,$relation_id = null): GoodsExportTemplate
    {
        $model = self::templateDetail($id,$plugin_id,$relation_id);
        $model->fill([
            'uniacid' => \YunShop::app()->uniacid,
            'name' => $data['name'] ?: '',
            'checked_column' => $data['checked_column'] ? implode(',',$data['checked_column']) : '',
        ]);
        $validator = $model->validator();
        if ($validator->fails()) {
            throw new AppException($validator->messages()->first());
        }
        if (!$model->save()) {
            throw new AppException('保存失败');
        }
        return $model;
    }

    public static function delTemplate($id,$plugin_id = null,$relation_id = null)
    {
        $model = self::templateDetail($id,$plugin_id,$relation_id);
        if (!$model->delete()) {
            throw new AppException('删除失败!');
        }
    }
}