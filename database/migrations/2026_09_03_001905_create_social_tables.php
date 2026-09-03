<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === Phase 2: profiles, friends, follow, block ===
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('password');
            $table->string('cover')->nullable()->after('avatar');
            $table->text('bio')->nullable()->after('cover');
            $table->string('work')->nullable()->after('bio');
            $table->string('education')->nullable()->after('work');
            $table->string('location')->nullable()->after('education');
            $table->date('birthday')->nullable()->after('location');
            $table->string('gender')->nullable()->after('birthday');
            $table->boolean('is_admin')->default(false)->after('gender');
            $table->string('status', 20)->default('active')->after('is_admin'); // active|suspended|banned|deactivated
            $table->timestamp('suspended_until')->nullable()->after('status');
            $table->timestamp('deactivated_at')->nullable()->after('suspended_until');
            $table->string('default_post_visibility', 20)->default('friends')->after('deactivated_at');
            $table->string('profile_visibility', 20)->default('friends')->after('default_post_visibility');
            $table->string('friend_request_privacy', 20)->default('everyone')->after('profile_visibility');
        });

        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending|accepted
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'friend_id']);
            $table->index(['friend_id', 'status']);
        });

        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['follower_id', 'followee_id']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'blocked_id']);
        });

        // === Phase 5: notifications ===
        Schema::create('site_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40); // friend_request|friend_accept|reaction|comment|follow|group_invite|page_post|share|tag|message...
            $table->string('category', 20)->default('general'); // friend|reaction|comment|follow|group|page|message|general
            $table->morphs('notifiable', 'site_notif_morph');
            $table->text('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        // === Phase 7: groups & pages (before posts FK) ===
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->string('privacy', 20)->default('public'); // public|private
            $table->boolean('requires_approval')->default(true);
            $table->text('rules')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->text('about')->nullable();
            $table->string('avatar')->nullable();
            $table->string('cover')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // === Phase 3: posts engine ===
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('shared_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->text('content')->nullable();
            $table->string('type', 20)->default('text'); // text|photo|video|reel|poll|share
            $table->string('visibility', 20)->default('friends'); // public|friends|private|custom
            $table->string('feeling')->nullable();
            $table->string('location')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_title')->nullable();
            $table->string('link_description')->nullable();
            $table->string('link_image')->nullable();
            $table->boolean('pinned')->default(false);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['type']);
            $table->index(['visibility']);
            $table->fullText(['content']);
        });

        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('mime', 40);
            $table->string('kind', 10); // image|video
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::create('post_visibility_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('post_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('text');
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['poll_option_id', 'user_id']);
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reactable');
            $table->string('type', 12); // like|love|care|haha|wow|sad|angry
            $table->timestamps();
            $table->unique(['user_id', 'reactable_type', 'reactable_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime', 40)->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['post_id', 'created_at']);
        });

        Schema::create('saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('savable');
            $table->timestamps();
            $table->unique(['user_id', 'savable_type', 'savable_id']);
        });

        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('tag')->unique();
            $table->unsignedBigInteger('posts_count')->default(0);
            $table->timestamps();
        });

        Schema::create('hashtag_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hashtag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unique(['hashtag_id', 'post_id']);
        });

        // === Phase 4: stories ===
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->default('photo'); // photo|video|text
            $table->string('media_path')->nullable();
            $table->string('text_content')->nullable();
            $table->string('background', 20)->nullable();
            $table->string('visibility', 20)->default('friends'); // public|friends|custom
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['expires_at']);
        });

        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['story_id', 'user_id']);
        });

        // === Phase 5: search ===
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('term', 100);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // === Phase 6: messenger ===
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(); // group chats
            $table->string('avatar_path')->nullable();
            $table->boolean('is_group')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime', 40)->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12);
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
        });

        // === Phase 7: group & page membership ===
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // owner|admin|member
            $table->string('status', 20)->default('pending'); // pending|active|banned
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('page_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('editor'); // admin|editor
            $table->timestamps();
            $table->unique(['page_id', 'user_id']);
        });

        Schema::create('page_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['page_id', 'user_id']);
        });

        // === Phase 8: reporting & moderation ===
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reportable');
            $table->string('reason', 40); // spam|harassment|nudity|violence|misinformation|hate|other
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open'); // open|reviewing|resolved|dismissed
            $table->text('moderator_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status']);
        });

        Schema::create('ai_flags', function (Blueprint $table) {
            $table->id();
            $table->morphs('flaggable');
            $table->decimal('risk_score', 4, 2)->default(0);
            $table->text('reasons')->nullable();
            $table->string('suggested_action', 30)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['risk_score']);
        });

        // === Phase 10: demo/demo tracking ===
        Schema::create('viewed_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            $table->unique(['user_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viewed_posts');
        Schema::dropIfExists('ai_flags');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('page_followers');
        Schema::dropIfExists('page_roles');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('search_histories');
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('hashtag_post');
        Schema::dropIfExists('hashtags');
        Schema::dropIfExists('saves');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('post_tags');
        Schema::dropIfExists('post_visibility_users');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('site_notifications');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('follows');
        Schema::dropIfExists('friendships');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar', 'cover', 'bio', 'work', 'education', 'location', 'birthday', 'gender',
                'is_admin', 'status', 'suspended_until', 'deactivated_at',
                'default_post_visibility', 'profile_visibility', 'friend_request_privacy',
            ]);
        });
    }
};
