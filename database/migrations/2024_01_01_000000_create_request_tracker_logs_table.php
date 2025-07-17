<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_tracker_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('method', 10);
            $table->text('url');
            $table->string('path');
            $table->json('headers')->nullable();
            $table->json('query')->nullable();
            $table->json('payload')->nullable();
            $table->ipAddress('ip');
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('timestamp');
            $table->decimal('execution_time', 8, 3)->default(0);
            $table->unsignedSmallInteger('response_status')->default(0);
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();

            $table->index('timestamp');
            $table->index(['user_id', 'timestamp']);
            $table->index(['response_status', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_tracker_logs');
    }
};
