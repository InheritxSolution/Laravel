<?php

namespace App\Http\Controllers\Api\Requests;

use Dingo\Api\Http\FormRequest;
use Dingo\Api\Http\Request;

class VerifyOTPRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return TRUE;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Request $request)
    {
		return [
		   'otp_number' => 'required|digits:4|numeric',
           'user_id' => 'required',
        ];
    }
	
	protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
		throw new \App\Exceptions\ApiHandler($validator->errors());
    }
	
	protected function failedAuthorization()
    {
		throw new \App\Exceptions\ApiHandler(['App' => 'Access Forbidden']);
    }
}
