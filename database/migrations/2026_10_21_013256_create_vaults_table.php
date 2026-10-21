<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key_salt', 128);
            $table->longText('wrapped_key');
            $table->unsignedInteger('kdf_opslimit');
            $table->unsignedBigInteger('kdf_memlimit');
            $table->unsignedTinyInteger('encryption_version')->default(1);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }
};
