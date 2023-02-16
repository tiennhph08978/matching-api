<?php

namespace App\Http\Requests\Common\ForgotPassword;

use Illuminate\Foundation\Http\FormRequest;

class CheckTokenResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:' . config('validate.string_max_length')]
        ];
    }
}
