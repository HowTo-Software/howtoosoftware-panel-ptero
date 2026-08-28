<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('howtoo_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->boolean('enabled')->default(false);
            $table->text('secret')->nullable();
            $table->string('model', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('howtoo_integrations');
    }
};
