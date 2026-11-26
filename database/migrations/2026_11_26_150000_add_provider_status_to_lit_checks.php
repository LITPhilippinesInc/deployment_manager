<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('lit_health_checks',function(Blueprint $t){$t->string('provider_status')->nullable();}); }
 public function down(): void { Schema::table('lit_health_checks',function(Blueprint $t){$t->dropColumn('provider_status');}); }
};
