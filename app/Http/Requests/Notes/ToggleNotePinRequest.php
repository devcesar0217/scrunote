<?php

namespace App\Http\Requests\Notes;

use Illuminate\Foundation\Http\FormRequest;

class ToggleNotePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:0']];
    }
}
