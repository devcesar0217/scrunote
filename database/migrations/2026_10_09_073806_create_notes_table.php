<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->json('content')->nullable();
            $table->longText('content_text')->nullable();

            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_pinned')->default(false);

            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            $table->index(
                ['user_id', 'updated_at'],
                'notes_user_updated_index'
            );

            $table->index(
                ['user_id', 'archived_at'],
                'notes_user_archived_index'
            );
        });

        Schema::create('note_tags', function (Blueprint $table) {
            $table->foreignId('note_id')
                ->constrained('notes')
                ->cascadeOnDelete();

            $table->foreignId('tag_id')
                ->constrained('tags')
                ->cascadeOnDelete();

            $table->primary(['note_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('note_tags');
    }
};
