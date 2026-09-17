<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\Hashtag;
use App\Models\Message;
use App\Models\Page;
use App\Models\Post;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Reaction;
use App\Models\Comment;
use App\Models\Report;
use App\Models\SiteNotification;
use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() > 1) {
            $this->command?->info('Demo data already present — skipping.');
            return;
        }

        // ---- people -------------------------------------------------------
        $people = collect([
            ['Aarav Sharma', 'aarav@bhasebook.test', 'Coffee ☕, code and cricket. Building things at Bhasebook.'],
            ['Meera Iyer', 'meera@bhasebook.test', 'Photographer 📷 chasing golden hour.'],
            ['Rohan Verma', 'rohan@bhasebook.test', 'Gym → work → repeat.'],
            ['Sara Khan', 'sara@bhasebook.test', 'Foodie. Will travel for biryani.'],
            ['Dev Patel', 'dev@bhasebook.test', 'Guitarist, gamer, occasional poet.'],
            ['Ananya Rao', 'ananya@bhasebook.test', 'Design + plants + long walks.'],
            ['Kabir Singh', 'kabir@bhasebook.test', 'Football on weekends. Fika on weekdays.'],
            ['Nisha Gupta', 'nisha@bhasebook.test', 'Reader. Baker. Cat person.'],
        ])->map(fn ($p) => User::create([
            'name' => $p[0],
            'email' => $p[1],
            'password' => Hash::make('Password@123'),
            'email_verified_at' => now(),
            'bio' => $p[2],
            'work' => 'Bhasebook Community',
            'location' => 'Bengaluru, India',
        ]));

        $admin = User::where('email', 'admin@bhasebook.test')->first();

        // demo@bhasebook.test is the showcase account
        $demo = User::create([
            'name' => 'Demo User',
            'email' => 'demo@bhasebook.test',
            'password' => Hash::make('Password@123'),
            'email_verified_at' => now(),
            'bio' => 'Just here for the vibes. Say hi!',
            'work' => 'Exploring Bhasebook',
            'location' => 'Hyderabad, India',
        ]);

        // ---- friendships: demo friends with everyone ----------------------
        foreach ($people as $p) {
            SocialService::sendFriendRequest($demo, $p);
            SocialService::acceptFriendRequest($p, $demo->id);
        }
        // a web of friendships among the others
        $links = [[0, 2], [1, 3], [2, 4], [3, 5], [4, 6], [5, 7], [0, 1], [6, 7]];
        foreach ($links as [$a, $b]) {
            SocialService::sendFriendRequest($people[$a], $people[$b]);
            SocialService::acceptFriendRequest($people[$b], $people[$a]->id);
        }
        // one pending request to demo
        SocialService::sendFriendRequest($people[7], $demo);

        // ---- groups -------------------------------------------------------
        $groups = collect([
            ['Bhasebook Coffee Club', 'public', true, 'Everything pour-overs, espresso and flat whites.'],
            ['Weekend Trekkers', 'public', false, 'Plan hikes, share trails, leave no trace.'],
            ['Indie Music Circle', 'private', true, 'For fans of sounds you won\'t hear on the radio.'],
        ])->map(fn ($g) => tap(Group::create([
            'name' => $g[0],
            'privacy' => $g[1],
            'requires_approval' => $g[2],
            'description' => $g[3],
            'created_by' => $people[0]->id,
            'rules' => "1. Be kind.\n2. Stay on topic.\n3. No spam.",
        ]), function (Group $group) use ($people, $demo) {
            $group->members()->attach($people[0]->id, ['role' => 'owner', 'status' => 'active']);
            foreach ($people->slice(1, 4) as $p) {
                $group->members()->attach($p->id, ['role' => 'member', 'status' => 'active']);
            }
            $group->members()->attach($demo->id, ['role' => 'member', 'status' => 'active']);
        }));

        // pending join request
        $groups[0]->members()->attach($people[7]->id, ['role' => 'member', 'status' => 'pending']);

        // ---- pages --------------------------------------------------------
        $pages = collect([
            ['Bhasebook Official', 'Community', 'News, updates and stories from the Bhasebook team.'],
            ['Trail & Summit Gear', 'Brand', 'Outdoor gear tested on real mountains.'],
            ['The Daily Grind', 'Entertainment', 'Memes, coffee and questionable life advice.'],
        ])->map(fn ($p) => tap(Page::create([
            'name' => $p[0],
            'category' => $p[1],
            'about' => $p[2],
            'created_by' => $people[1]->id,
        ]), function (Page $page) use ($people, $demo) {
            $page->roles()->attach($people[1]->id, ['role' => 'admin']);
            $page->roles()->attach($people[2]->id, ['role' => 'editor']);
            $page->followers()->attach([$people[0]->id, $people[3]->id, $people[5]->id, $demo->id]);
        }));

        // ---- posts ---------------------------------------------------------
        $makePost = function (User $u, array $attrs) {
            $post = Post::create(array_merge([
                'user_id' => $u->id,
                'type' => 'text',
                'visibility' => 'public',
                'created_at' => now()->subHours(rand(1, 72)),
            ], $attrs));
            foreach (['#bhasebook', '#community'] as $tag) {
                $hashtag = Hashtag::firstOrCreate(['tag' => $tag]);
                $post->hashtags()->syncWithoutDetaching([$hashtag->id]);
                $hashtag->increment('posts_count');
            }
            return $post;
        };

        // a spammer whose content lands in the moderation queue (created early so it sinks in the feed)
        $spammer = User::create([
            'name' => 'Spam Bot', 'email' => 'spammer@bhasebook.test',
            'password' => Hash::make('Password@123'), 'email_verified_at' => now(), 'bio' => 'BUY FOLLOWERS NOW!!!',
        ]);
        $spamPost = $makePost($spammer, ['content' => 'Free crypto giveaway, send 0.1 BTC get 10 back!!! buy followers cheap', 'created_at' => now()->subHours(12)]);


        $posts = collect();
        $posts->push($makePost($people[0], ['content' => 'Morning brewing experiment: 1:16 ratio, 94°C, 3 minutes. The cup was incredible. ☕ #bhasebook']));
        $posts->push($makePost($people[1], ['content' => 'Golden hour at Nandi Hills never disappoints. Swipe-worthy light today. 🌄 #bhasebook']));
        $posts->push($makePost($people[4], ['content' => 'Wrote a new riff last night at 2am. It either slaps or I was very tired. Jury is out. 🎸']));
        $posts->push($makePost($demo, ['content' => 'Day 1 on Bhasebook. This place actually feels friendly. 👋 #community']));
        $posts->push($makePost($people[3], ['content' => 'Hyderabad biryani ranking thread — drop your top 3. I need data for science. 🍛']));

        // poll post
        $poll = $makePost($people[2], ['content' => 'Weekend plans: gym or recovery day?', 'type' => 'poll']);
        $optA = PollOption::create(['post_id' => $poll->id, 'text' => 'Gym, obviously']);
        $optB = PollOption::create(['post_id' => $poll->id, 'text' => 'Recovery day']);
        PollVote::create(['poll_option_id' => $optA->id, 'user_id' => $people[0]->id]);
        PollVote::create(['poll_option_id' => $optB->id, 'user_id' => $people[5]->id]);
        PollVote::create(['poll_option_id' => $optB->id, 'user_id' => $demo->id]);

        // page posts
        $pagePost = Post::create([
            'page_id' => $pages[0]->id, 'user_id' => null, 'type' => 'text', 'visibility' => 'public',
            'content' => 'Welcome to Bhasebook! Reactions, stories, reels and messenger are all live. Tell us what to build next. 🚀',
            'created_at' => now()->subHours(2),
        ]);
        Post::create([
            'page_id' => $pages[1]->id, 'user_id' => null, 'type' => 'text', 'visibility' => 'public',
            'content' => 'New season, new trails. The Ridge 40L is back in stock — tested on Kudremukh last month.',
            'created_at' => now()->subHours(30),
        ]);

        // group post
        Post::create([
            'group_id' => $groups[0]->id, 'user_id' => $people[0]->id, 'type' => 'text', 'visibility' => 'public',
            'content' => 'This Saturday: cupping session at the café on 5th. Bring your favourite beans!',
            'created_at' => now()->subHours(10),
        ]);

        // ---- reels & watch videos ------------------------------------------
        // A real (generated) demo reel ships with the repo so the Reels and
        // Watch pages have playable content. Replace with real clips anytime.
        $reelVideo = 'seed/demo-reel.webm';
        if (! \Storage::disk('public')->exists($reelVideo)) {
            $src = database_path('seeders/fixtures/demo-reel.webm');
            if (is_file($src)) {
                \Storage::disk('public')->put($reelVideo, file_get_contents($src));
            }
        }
        if (\Storage::disk('public')->exists($reelVideo)) {
            $reelPosts = collect([
                [$people[1], 'Golden hour timelapse from Sunday ride 🌄'],
                [$people[4], 'That 2am riff, take 3 🎸'],
                [$people[5], 'Buttercream piping speedrun 🧁'],
            ])->map(fn ($r) => tap(Post::create([
                'user_id' => $r[0]->id, 'type' => 'reel', 'visibility' => 'public',
                'content' => $r[1], 'created_at' => now()->subHours(rand(1, 48)),
            ]), function (Post $post) use ($reelVideo, $people, $demo) {
                $post->media()->create([
                    'path' => $reelVideo, 'mime' => 'video/webm', 'kind' => 'video',
                ]);
                Reaction::create(['reactable_type' => Post::class, 'reactable_id' => $post->id, 'user_id' => $demo->id, 'type' => 'love']);
                Reaction::create(['reactable_type' => Post::class, 'reactable_id' => $post->id, 'user_id' => $people[0]->id, 'type' => 'like']);
            }));
            // one regular video post for the Watch page
            $watchPost = Post::create([
                'user_id' => $people[3]->id, 'type' => 'video', 'visibility' => 'public',
                'content' => 'Biryani layer-cut in slow motion. You are welcome. 🍛',
                'created_at' => now()->subHours(6),
            ]);
            $watchPost->media()->create(['path' => $reelVideo, 'mime' => 'video/webm', 'kind' => 'video']);
        }


        // reactions
        foreach ($posts->take(4) as $post) {
            Reaction::create(['reactable_type' => Post::class, 'reactable_id' => $post->id, 'user_id' => $demo->id, 'type' => collect(['like', 'love', 'haha'])->random()]);
        }
        Reaction::create(['reactable_type' => Post::class, 'reactable_id' => $posts[0]->id, 'user_id' => $people[1]->id, 'type' => 'love']);
        Reaction::create(['reactable_type' => Post::class, 'reactable_id' => $posts[0]->id, 'user_id' => $people[2]->id, 'type' => 'like']);
        Reaction::create(['reactable_type' => Post::class, 'reactable_id' => $posts[4]->id, 'user_id' => $people[0]->id, 'type' => 'wow']);

        // comments
        $c1 = Comment::create(['post_id' => $posts[0]->id, 'user_id' => $people[1]->id, 'content' => 'Trying this tomorrow morning!']);
        Comment::create(['post_id' => $posts[0]->id, 'user_id' => $people[2]->id, 'content' => '94°C is the sweet spot, agreed.', 'parent_id' => $c1->id]);
        Comment::create(['post_id' => $pagePost->id, 'user_id' => $demo->id, 'content' => 'Dark mode is chef\'s kiss. 👌']);

        // ---- stories -------------------------------------------------------
        Story::create(['user_id' => $people[1]->id, 'kind' => 'text', 'text_content' => 'On a mountain, kind of. ⛰️', 'background' => 'forest', 'visibility' => 'friends', 'expires_at' => now()->addHours(20)]);
        Story::create(['user_id' => $people[3]->id, 'kind' => 'text', 'text_content' => 'Biryani quest: day 12', 'background' => 'sunset', 'visibility' => 'public', 'expires_at' => now()->addHours(18)]);
        $ownStory = Story::create(['user_id' => $demo->id, 'kind' => 'text', 'text_content' => 'First day energy ✨', 'background' => 'bhas', 'visibility' => 'public', 'expires_at' => now()->addHours(23)]);
        StoryView::firstOrCreate(['story_id' => $ownStory->id, 'user_id' => $people[0]->id]);

        // ---- messenger -----------------------------------------------------
        $conv = Conversation::create(['is_group' => false, 'created_by' => $demo->id, 'last_activity_at' => now()->subMinutes(5)]);
        $conv->participants()->attach([$demo->id, $people[0]->id]);
        Message::create(['conversation_id' => $conv->id, 'sender_id' => $people[0]->id, 'body' => 'Yo! Welcome to Bhasebook 🎉', 'created_at' => now()->subMinutes(20)]);
        Message::create(['conversation_id' => $conv->id, 'sender_id' => $demo->id, 'body' => 'Thanks! Loving it so far.', 'created_at' => now()->subMinutes(18)]);
        Message::create(['conversation_id' => $conv->id, 'sender_id' => $people[0]->id, 'body' => 'Wait till you try the reels 👀', 'created_at' => now()->subMinutes(5)]);

        $group2 = Conversation::create(['title' => 'Coffee Club ☕', 'is_group' => true, 'created_by' => $people[0]->id, 'last_activity_at' => now()->subHours(2)]);
        $group2->participants()->attach([$demo->id, $people[0]->id, $people[1]->id, $people[3]->id]);
        Message::create(['conversation_id' => $group2->id, 'sender_id' => $people[0]->id, 'body' => 'Cupping Saturday — who is in?', 'created_at' => now()->subHours(3)]);
        Message::create(['conversation_id' => $group2->id, 'sender_id' => $people[3]->id, 'body' => 'Only if there is food after 🍩', 'created_at' => now()->subHours(2)]);
        // unread for demo
        $demoPivot = $group2->participants()->where('user_id', $demo->id)->first()->pivot;

        // ---- notifications for demo ----------------------------------------
        SiteNotification::create(['user_id' => $demo->id, 'actor_id' => $people[4]->id, 'type' => 'reaction', 'category' => 'reaction', 'notifiable_type' => Post::class, 'notifiable_id' => $posts[3]->id, 'data' => json_encode(['text' => 'reacted to your post'])]);
        SiteNotification::create(['user_id' => $demo->id, 'actor_id' => $people[1]->id, 'type' => 'comment', 'category' => 'comment', 'notifiable_type' => Post::class, 'notifiable_id' => $posts[3]->id, 'data' => json_encode(['text' => 'commented on your post'])]);
        SiteNotification::create(['user_id' => $demo->id, 'actor_id' => $people[7]->id, 'type' => 'friend_request', 'category' => 'friend', 'notifiable_type' => User::class, 'notifiable_id' => $people[7]->id, 'data' => json_encode(['text' => 'sent you a friend request'])]);
        SiteNotification::create(['user_id' => $demo->id, 'actor_id' => $people[0]->id, 'type' => 'message', 'category' => 'message', 'data' => json_encode(['text' => 'messaged you: Wait till you try the reels 👀'])]);

        // ---- moderation queue ----------------------------------------------
        Report::create(['reporter_id' => $people[0]->id, 'reportable_type' => Post::class, 'reportable_id' => $spamPost->id, 'reason' => 'spam', 'details' => 'obvious scam']);
        Report::create(['reporter_id' => $people[1]->id, 'reportable_type' => Post::class, 'reportable_id' => $spamPost->id, 'reason' => 'spam']);

        // one AI flag from the moderation heuristic
        \App\Jobs\ModerateContent::dispatchSync(Post::class, $spamPost->id, $spamPost->content);

        // group invite pending for demo
        GroupInvite::create(['group_id' => $groups[1]->id, 'user_id' => $demo->id, 'invited_by' => $people[2]->id]);

        $this->command?->info('Demo data seeded: '.(User::count()).' users, '.Post::count().' posts, '.Group::count().' groups, '.Page::count().' pages.');
    }
}
