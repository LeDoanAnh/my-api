<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'username'      => 'required|string|unique:users,username|max:255',
            'password'      => 'required|string|min:6',
            'department_id' => 'required|exists:departments,id',
            'role_ids'      => 'required|array',
            'role_ids.*'    => 'exists:roles,id',
            'status'        => 'required|string',
        ];
    }

    // ✅ Thêm method này - trả JSON thay vì redirect khi validation fail
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
