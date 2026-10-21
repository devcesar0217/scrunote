<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->unsignedBigInteger('lock_version')->default(0)->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
    }
};
