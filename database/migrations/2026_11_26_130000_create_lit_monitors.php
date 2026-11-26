<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('lit_monitors', function(Blueprint $t) {
   $t->id(); $t->unsignedBigInteger('team_id')->index(); $t->string('name'); $t->string('url',2048); $t->boolean('enabled')->default(true); $t->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('lit_monitors'); }
};
