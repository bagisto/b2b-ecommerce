<?php

namespace Webkul\B2BSuite\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webkul\B2BSuite\Models\CustomerQuote;

class QuoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $statuses = $this->routeIs('b2b.shop.quotes.store')
            ? [CustomerQuote::STATUS_DRAFT, CustomerQuote::STATUS_OPEN]
            : array_keys(CustomerQuote::STATUS_LABEL_CLASSES);

        $formats = str_replace(' ', '', core()->getConfigData('b2b.quotes.settings.supported_file_formats') ?: 'doc,docx,xls,xlsx,pdf,txt,jpg,png,jpeg');

        $maxFileSize = (int) (core()->getConfigData('b2b.quotes.settings.maximum_file_size') ?: 10);

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['sometimes', 'required', Rule::in($statuses)],
            'cart_id' => ['required'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['file', 'mimes:'.$formats, 'max:'.($maxFileSize * 1024)],
        ];
    }
}
