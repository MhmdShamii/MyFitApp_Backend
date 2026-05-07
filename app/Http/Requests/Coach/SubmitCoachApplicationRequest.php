<?php

namespace App\Http\Requests\Coach;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCoachApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description'    => 'required|string|min:50|max:1000',
            'documents'      => 'required|array|min:1|max:10',
            'documents.*'    => 'required|file|mimes:pdf,jpeg,png,jpg|max:10240',
        ];
    }
}
