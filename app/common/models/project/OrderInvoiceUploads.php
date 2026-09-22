<?php

namespace app\common\models\project;
use app\common\models\Address;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
use app\common\models\project\Floors;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderInvoiceUploads extends BaseModel
{

   // use SoftDeletes;
    protected $table = 'yz_order_invoice_uploads';
    public $fillable = ["order_invoice_id","invoice_category","invoice_amount","amount_without_tax","tax_amount","tax_rate"];
    public static function insertInvoiceUpload($orderInvoiceId, $invoiceCategory, $invoiceAmount, $invoice_type)
    {
        // 计算不含税金额和税额
        $tax = TaxRate::where('type',$invoiceCategory)->first();
        $taxRate = $invoice_type == 1?$tax->general_rate:$tax->rate;
        $amountWithoutTax = self::truncateDecimal($invoiceAmount / (1 + $taxRate / 100), 2);
        $taxAmount = self::truncateDecimal($amountWithoutTax * ($taxRate / 100), 2);
        $save['order_invoice_id'] = $orderInvoiceId;
        $save['invoice_category'] = $invoiceCategory;
        $save['invoice_amount'] = $invoiceAmount;
        $save['amount_without_tax'] = $amountWithoutTax;
        $save['tax_amount'] = $taxAmount;
        $save['tax_rate'] = $taxRate;

        return self::create($save);
    }


    private static function truncateDecimal($number, $precision = 2)
    {
        $numStr = (string) $number;
        if (strpos($numStr, '.') !== false) {
            $parts = explode('.', $numStr);
            $decimal = substr($parts[1], 0, $precision);
            return $parts[0] . '.' . str_pad($decimal, $precision, '0');
        } else {
            return $numStr . '.' . str_repeat('0', $precision);
        }
    }

}