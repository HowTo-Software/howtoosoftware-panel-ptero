<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('howtoo_integrations', function (Blueprint $table) {
            $table->unsignedSmallInteger('timeout_seconds')->default(25)->after('environment_key_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('howtoo_integrations', function (Blueprint $table) {
            $table->dropColumn('timeout_seconds');
        });
    }
};
