<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'reportable_type' => 'required|in:post,comment,user,group,page,message',
            'reportable_id' => 'required|integer',
            'reason' => 'required|in:'.implode(',', Report::REASONS),
            'details' => 'nullable|string|max:1000',
        ]);

        $map = [
            'post' => \App\Models\Post::class,
            'comment' => \App\Models\Comment::class,
            'user' => \App\Models\User::class,
            'group' => \App\Models\Group::class,
            'page' => \App\Models\Page::class,
            'message' => \App\Models\Message::class,
        ];
        $class = $map[$data['reportable_type']];
        abort_unless($class::where('id', $data['reportable_id'])->exists(), 404, 'Item not found.');

        // dedupe: one open report per user per item
        $existing = Report::where('reporter_id', $me->id)
            ->where('reportable_type', $class)
            ->where('reportable_id', $data['reportable_id'])
            ->where('status', 'open')->first();
        if ($existing) {
            return back()->with('status', 'You already reported this — our team is reviewing it.');
        }

        Report::create([
            'reporter_id' => $me->id,
            'reportable_type' => $class,
            'reportable_id' => $data['reportable_id'],
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        // queue AI moderation assist for text-bearing content
        $model = $class::find($data['reportable_id']);
        $text = match ($class) {
            \App\Models\Post::class => $model?->content,
            \App\Models\Comment::class => $model?->content,
            \App\Models\Message::class => $model?->body,
            \App\Models\Group::class => $model?->name.' '.($model?->description ?? ''),
            \App\Models\Page::class => $model?->name.' '.($model?->about ?? ''),
            default => null,
        };
        if ($text !== null && trim($text) !== '') {
            \App\Jobs\ModerateContent::dispatch($class, (int) $data['reportable_id'], $text);
        }

        return back()->with('status', 'Report submitted. Thank you for keeping Bhasebook safe.');
    }
}
