<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title', '')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required', 'string', 'max:120', 'not_regex:/\x00/',
                Rule::unique('projects', 'title')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'description' => ['nullable', 'string', 'max:2000', 'not_regex:/\x00/'],
        ];
    }
}
