<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-user notification preferences + AI quota bookkeeping
        if (! Schema::hasColumn('users', 'notification_prefs')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('notification_prefs')->nullable()->after('friend_request_privacy');
            });
        }
        if (! Schema::hasColumn('users', 'verified')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('verified')->default(false)->after('notification_prefs');
            });
        }

        // Group invitations
        if (! Schema::hasTable('group_invites')) {
            Schema::create('group_invites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
                $table->string('status', 20)->default('pending'); // pending|accepted|declined
                $table->timestamps();
                $table->unique(['group_id', 'user_id']);
            });
        }

        // Admin warnings issued to users
        if (! Schema::hasTable('user_warnings')) {
            Schema::create('user_warnings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason', 200);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        // Platform settings (key/value, editable from admin)
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // AI assistant usage (per-user daily quota)
        if (! Schema::hasTable('ai_requests')) {
            Schema::create('ai_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('mode', 30);
                $table->boolean('succeeded')->default(true);
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }

        // Hidden/snoozed recommendations feedback
        if (! Schema::hasTable('recommendation_feedback')) {
            Schema::create('recommendation_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('kind', 30); // user|group|page|post
                $table->unsignedBigInteger('item_id');
                $table->string('action', 20)->default('hide'); // hide
                $table->timestamps();
                $table->unique(['user_id', 'kind', 'item_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_feedback');
        Schema::dropIfExists('ai_requests');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('user_warnings');
        Schema::dropIfExists('group_invites');
        if (Schema::hasColumn('users', 'notification_prefs')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('notification_prefs');
            });
        }
        if (Schema::hasColumn('users', 'verified')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('verified');
            });
        }
    }
};
