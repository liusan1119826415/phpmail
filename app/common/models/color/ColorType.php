<?php

namespace app\common\models\color;

use app\framework\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use app\common\models\BaseModel;
use app\common\models\Category;
class  ColorType extends BaseModel
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];


    public $table = 'yz_color_type';

    protected $guarded = [];


    public static function deletedColorType($id)
    {
        return self::where('id', $id)
            ->delete();
    }


    public function belongsToCategory(){
        return $this->belongsTo(Category::class,"category_id","id");
    }



    public static function getColorType($search = [])
    {

        $result = self::uniacid()->whereNull('deleted_at');

        if ($search['id']){
            $result->where('id',$search['id']);
        }
        if ($search['name']){
            $result->where('name','like','%'.$search['name'] .'%');
        }
        return $result;
    }

    /**
     *  定义字段名
     * 可使
     * @return array */
    public  function atributeNames() {
        return [
            'name'=> '色板类型名称',
        ];
    }

    /**
     * 字段规则
     * @return array */
    public  function rules() {
        return [
            'name' => 'required',
        ];
    }




}
