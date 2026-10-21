<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => filled($this->input('title')) ? trim((string) $this->input('title')) : null,
            'markdown' => str_replace(["\r\n", "\r"], "\n", (string) $this->input('markdown', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:160', 'not_regex:/\x00/'],
            'markdown' => ['required', 'string', 'max:250000', 'not_regex:/\x00/'],
            'tags' => ['nullable', 'string', 'max:650', 'not_regex:/\x00/'],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }
}
