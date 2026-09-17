<?php

namespace App\Jobs;

use App\Models\AiFlag;
use App\Services\AiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ModerateContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(
        public string $flaggableType,
        public int $flaggableId,
        public string $text,
    ) {}

    public function handle(AiService $ai): void
    {
        try {
            $verdict = $ai->moderate($this->text);
            if ($verdict['risk'] > 0.35 || ! empty($verdict['reasons'])) {
                AiFlag::updateOrCreate(
                    ['flaggable_type' => $this->flaggableType, 'flaggable_id' => $this->flaggableId],
                    [
                        'risk_score' => $verdict['risk'],
                        'reasons' => $verdict['reasons'],
                        'suggested_action' => $verdict['suggestion'],
                    ],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AI moderation skipped: '.$e->getMessage());
        }
    }
}
