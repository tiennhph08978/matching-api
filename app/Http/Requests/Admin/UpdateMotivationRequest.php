<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMotivationRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'motivation' => ['nullable', 'string', 'max:' . config('validate.text_max_length_information_pr')],
            'noteworthy' => ['nullable', 'string', 'max:' . config('validate.text_max_length_information_pr')],
        ];
    }
}
