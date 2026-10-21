<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('title', 160);
            $table->string('url', 2048)->nullable();
            $table->string('username', 320)->nullable();
            $table->string('secret_kind', 24);
            $table->json('metadata')->nullable();
            $table->longText('sensitive_payload');
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['vault_id', 'is_pinned', 'updated_at'], 'vault_entries_vault_pin_updated_index');
            $table->index(['vault_id', 'title'], 'vault_entries_vault_title_index');
        });

        Schema::create('vault_entry_tags', function (Blueprint $table) {
            $table->foreignId('vault_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['vault_entry_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_entry_tags');
        Schema::dropIfExists('vault_entries');
    }
};
