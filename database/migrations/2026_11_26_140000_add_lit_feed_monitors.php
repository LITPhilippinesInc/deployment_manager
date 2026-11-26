<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('lit_monitors',function(Blueprint $t){$t->string('type')->default('http'); $t->unsignedInteger('interval_minutes')->default(1);});
  Schema::create('lit_monitor_incidents',function(Blueprint $t){
   $t->id(); $t->foreignId('monitor_id')->constrained('lit_monitors')->cascadeOnDelete(); $t->string('fingerprint',64); $t->text('title'); $t->text('description'); $t->text('published')->nullable(); $t->timestamps(); $t->unique(['monitor_id','fingerprint']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('lit_monitor_incidents');
  Schema::table('lit_monitors',function(Blueprint $t){$t->dropColumn(['type','interval_minutes']);});
 }
};
