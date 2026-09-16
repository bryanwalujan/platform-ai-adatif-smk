<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pbl_projects', function (Blueprint $table) {
            $table->json('attachments')->nullable();
            $table->decimal('score', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pbl_projects', fn (Blueprint $table) => $table->dropColumn('attachments'));
    }
};
