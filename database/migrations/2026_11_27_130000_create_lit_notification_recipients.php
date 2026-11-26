<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('lit_notification_recipients', function(Blueprint $table) {
   $table->unsignedBigInteger('team_id');
   $table->unsignedBigInteger('user_id');
   $table->primary(['team_id','user_id']);
  });
 }
 public function down(): void { Schema::dropIfExists('lit_notification_recipients'); }
};
