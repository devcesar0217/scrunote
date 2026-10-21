<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $heading = fake()->sentence(3);
        $body = fake()->paragraph();

        return [
            'user_id' => User::factory(),
            'title' => $heading,
            'markdown' => "# {$heading}\n\n{$body}",
            'content_text' => "{$heading}\n\n{$body}",
            'is_pinned' => false,
            'lock_version' => 0,
        ];
    }
}
