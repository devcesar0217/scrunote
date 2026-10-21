<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Note> */
class NoteFactory extends Factory
{
    public function definition(): array
    {
        $body = fake()->paragraph();

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'content' => ['html' => '<p>'.e($body).'</p>'],
            'content_text' => $body,
            'is_favorite' => false,
            'is_pinned' => false,
            'archived_at' => null,
            'lock_version' => 0,
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn (): array => ['is_pinned' => true]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
