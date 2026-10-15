<?php

namespace App\Modules\Assistant\Application;

use App\Exceptions\AssistantUnavailable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AssistantRequestBudget
{
    public function reserve(User $user): void
    {
        try {
            Cache::lock('assistant:budget:lock:v1', 10)->block(1, function () use ($user): void {
                $now = CarbonImmutable::now('UTC');
                $periods = [
                    ['assistant:requests:day:'.$now->format('Ymd'), config('assistant.daily_requests'), $now->addDay()->startOfDay()],
                    ['assistant:requests:month:'.$now->format('Ym'), config('assistant.monthly_requests'), $now->addMonthNoOverflow()->startOfMonth()],
                    ['assistant:requests:user:'.hash('sha256', (string) $user->getKey()).':'.$now->format('Ymd'), config('assistant.user_daily_requests'), $now->addDay()->startOfDay()],
                ];
                foreach ($periods as [$key, $limit]) {
                    if ((int) Cache::get($key, 0) >= (int) $limit) {
                        throw new AssistantUnavailable('The Assistant allowance has been reached. Try again later or contact your administrator.', 429);
                    }
                }
                foreach ($periods as [$key, $limit, $reset]) {
                    if (Cache::put($key, (int) Cache::get($key, 0) + 1, $reset) !== true) {
                        throw new \RuntimeException('Assistant allowance could not be saved.');
                    }
                }
            });
        } catch (AssistantUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Assistant allowance could not be verified.', ['exception_type' => $exception::class]);
            throw new AssistantUnavailable('The Assistant is temporarily unavailable. Your office records are unaffected. Try again shortly.');
        }
    }
}
