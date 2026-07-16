<?php

namespace App\Http\Requests\Vouchers;

use Illuminate\Foundation\Http\FormRequest;

class ImportVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'    => ['required', 'integer'],
            'brand_name'    => ['required', 'string', 'max:255'],
            'denomination'  => ['required', 'numeric', 'min:1'],
            'quantity'      => ['required', 'integer', 'min:1', 'max:1000'],
            'currency_code' => ['nullable', 'string', 'max:8'],
            'image_url'     => ['nullable', 'string', 'max:1000'],
        ];
    }
}
