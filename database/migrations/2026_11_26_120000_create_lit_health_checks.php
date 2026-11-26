<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('lit_health_checks', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->timestamp('checked_at');
            $table->boolean('reachable');
            $table->unsignedSmallInteger('code')->nullable();
            $table->unsignedInteger('ms')->nullable();
            $table->unique(['provider', 'checked_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('lit_health_checks'); }
};
