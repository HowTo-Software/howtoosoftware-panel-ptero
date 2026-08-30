<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('howtoo_integrations', function (Blueprint $table) {
            $table->string('base_url', 255)->nullable()->after('model');
        });

        DB::table('howtoo_integrations')
            ->whereIn('provider', ['gemini', 'groq'])
            ->update(['enabled' => false, 'updated_at' => now()]);

        DB::table('howtoo_integrations')->insertOrIgnore([
            [
                'provider' => 'ollama',
                'enabled' => true,
                'priority' => 10,
                'environment_key_enabled' => true,
                'timeout_seconds' => 90,
                'secret' => null,
                'model' => 'hermes:70B',
                'base_url' => 'http://92.168.1.252:11435',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('howtoo_integration_keys')->where('provider', 'ollama')->delete();
        DB::table('howtoo_integrations')->where('provider', 'ollama')->delete();

        Schema::table('howtoo_integrations', function (Blueprint $table) {
            $table->dropColumn('base_url');
        });
    }
};
