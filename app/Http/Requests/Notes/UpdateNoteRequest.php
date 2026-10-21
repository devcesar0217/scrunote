<?php

namespace App\Http\Requests\Notes;

use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is resolved to a 404 in the controller before the policy
        // check, avoiding confirmation that another user's note exists.
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $content = (string) $this->input('content_html', '');

        if ($this->boolean('content_plain')) {
            $content = nl2br(e($content), false);
        }

        $this->merge([
            'title' => filled($this->input('title')) ? trim((string) $this->input('title')) : null,
            'content_html' => $content,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:160', 'not_regex:/\x00/'],
            'content_html' => ['required', 'string', 'max:250000', 'not_regex:/\x00/'],
            'tags' => ['nullable', 'string', 'max:650', 'not_regex:/\x00/'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $this->user()?->id)],
            'version' => ['required', 'integer', 'min:0'],
            'content_plain' => ['nullable', 'boolean'],
        ];
    }
}
