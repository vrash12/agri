<?php

namespace App\Http\Controllers;

use App\Exceptions\AssistantUnavailable;
use App\Http\Requests\AssistantMessageRequest;
use App\Support\AssistantConversation;
use App\Support\AuditTrail;
use Illuminate\Http\JsonResponse;

final class AssistantController extends Controller
{
    public function __invoke(AssistantMessageRequest $request, AssistantConversation $conversation): JsonResponse
    {
        $data = $request->validated();
        try {
            $started = hrtime(true);
            $result = $conversation->answer($request->user(), $data['message'], $data['module'],
                $request->hasSession() ? $request->session()->getId() : '', $data['context'] ?? null, $data['choice'] ?? null);
            $metadata = $result['_audit'];
            $metadata['latency_ms'] = (int) round((hrtime(true) - $started) / 1000000);
            unset($result['_audit']);
            AuditTrail::record('viewed', 'Office Assistant', 'Used the scoped office Assistant.', ['metadata' => $metadata]);

            return response()->json($result)->header('Cache-Control', 'private, no-store');
        } catch (AssistantUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }
    }
}
