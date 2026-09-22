<?php

namespace app\backend\modules\export\controllers;

use app\backend\modules\export\models\ExportRecord;
use app\backend\modules\export\models\ExportTemplate;
use Darabonba\GatewaySpi\Models\InterceptorContext\request;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class IndexController extends \app\common\components\BaseController
{

    public function index()
    {
        return view('export.index', [])->render();
    }

    public function exec()
    {
        $request = request()->post();
        $template_id = $request['template_id'];
        $export_type = $request['export_type'];
        $columns = ExportTemplate::where('id', $template_id)->value('columns');
        ExportRecord::create([
            'template_id' => $template_id,
            'export_type' => $export_type,
            'status' => 0,
            'uniacid' => \YunShop::app()->uniacid,
            'columns' => $columns,
            'request' => $request,
            'user_id' => \YunShop::app()->uid,

        ]);
        return $this->successJson('导出成功');
    }

    public function getList()
    {
        $search = request()->input('search') ?? [];
        $list = ExportRecord::getlist($search)->select(['id', 'export_type', 'total', 'user_id', 'status', 'export_type', 'created_at'])
            ->when(\YunShop::app()->role == 'clerk',function ($query) {
                $query->where('user_id',\YunShop::app()->uid);
            })
            ->with('hasOneUser')
            ->orderBy('id','desc')
            ->paginate(15)
            ->toArray();
        return $this->successJson('ok', $list);
    }


    public function download()
    {
        $id = request()->input('id');
        $record = ExportRecord::where('id',$id)->select(['source_name','download_name'])->first();
        return response()->download(Storage::disk('export')->path($record->source_name),$record->download_name);
    }

    public function getType()
    {

        return $this->successJson('', app('Export')->getTypes());
    }

    public function delete()
    {
        $ids = request()->input('ids');
        foreach ($ids as $id) {
            ($export = ExportRecord::where('id',$id)->first()) && $export->delete();
        }
        return $this->successJson('删除成功');
    }

}
