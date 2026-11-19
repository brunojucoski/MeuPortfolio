<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255',
                Rule::notIn([Usuario::EMAIL_VISITANTE_NAO_IDENTIFICADO]),
                Rule::unique(Usuario::class, 'email')->ignore($this->user()->id)],
        ];
    }
}
