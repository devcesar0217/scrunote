<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'title'], 'projects_user_title_unique');
            $table->index(['user_id', 'updated_at'], 'projects_user_updated_index');
        });

        Schema::table('notes', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained('projects')->nullOnDelete();
            $table->index(['user_id', 'project_id', 'updated_at'], 'notes_user_project_updated_index');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained('projects')->nullOnDelete();
            $table->index(['user_id', 'project_id', 'updated_at'], 'documents_user_project_updated_index');
        });

        Schema::table('vault_entries', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('vault_id')->constrained('projects')->nullOnDelete();
            $table->index(['project_id', 'updated_at'], 'vault_entries_project_updated_index');
        });
    }

    public function down(): void
    {
        // Drop foreign keys before their supporting custom indexes. MySQL may
        // select these indexes for the constraint, so reversing that order
        // prevents rollback on a fresh database.
        Schema::table('vault_entries', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropIndex('vault_entries_project_updated_index');
            $table->dropColumn('project_id');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropIndex('documents_user_project_updated_index');
            $table->dropColumn('project_id');
        });

        Schema::table('notes', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropIndex('notes_user_project_updated_index');
            $table->dropColumn('project_id');
        });

        Schema::dropIfExists('projects');
    }
};
