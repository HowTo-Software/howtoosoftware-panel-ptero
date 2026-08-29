<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('howtoo_integrations', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')->default(100)->after('enabled');
            $table->boolean('environment_key_enabled')->default(true)->after('priority');
        });

        Schema::create('howtoo_integration_keys', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('name', 80);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->text('secret');
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->string('last_failure_reason', 32)->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamps();

            $table->index(['provider', 'enabled', 'priority'], 'howtoo_keys_provider_enabled_priority');
            $table->index('cooldown_until');
        });

        DB::table('howtoo_integrations')
            ->whereNotNull('secret')
            ->orderBy('id')
            ->each(function (object $integration): void {
                DB::table('howtoo_integration_keys')->insert([
                    'provider' => $integration->provider,
                    'name' => 'Primary',
                    'enabled' => true,
                    'priority' => 10,
                    // The ciphertext is copied as-is and remains protected by APP_KEY.
                    'secret' => $integration->secret,
                    'failure_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('howtoo_integrations')->whereNotNull('secret')->update(['secret' => null]);
        DB::table('howtoo_integrations')->where('provider', 'gemini')->update(['priority' => 10]);
        DB::table('howtoo_integrations')->where('provider', 'groq')->update(['priority' => 20]);
    }

    public function down(): void
    {
        DB::table('howtoo_integrations')->orderBy('id')->each(function (object $integration): void {
            $key = DB::table('howtoo_integration_keys')
                ->where('provider', $integration->provider)
                ->orderBy('priority')
                ->orderBy('id')
                ->first();

            if ($key) {
                DB::table('howtoo_integrations')->where('id', $integration->id)->update(['secret' => $key->secret]);
            }
        });

        Schema::dropIfExists('howtoo_integration_keys');
        Schema::table('howtoo_integrations', function (Blueprint $table) {
            $table->dropColumn(['priority', 'environment_key_enabled']);
        });
    }
};
