<?php

namespace App\Http\Controllers;

use App\Services\AiAgentService;
use App\Services\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AiController extends Controller
{
    public function chat(Request $request, OpenAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        return $this->respond(function () use ($ai, $data, $request) {
            $turn = $ai->assistantTurn($data['message'], $data['history'] ?? [], $request->user());

            return [
                'reply' => $turn['reply'],
                'proposal' => $turn['proposal'],
            ];
        });
    }

    public function confirm(Request $request, AiAgentService $agent): JsonResponse
    {
        $data = $request->validate([
            'proposal_id' => ['required', 'uuid'],
        ]);

        return $this->respond(function () use ($agent, $request, $data) {
            $result = $agent->confirm($request->user(), $data['proposal_id']);

            return [
                'reply' => $result['reply'],
                'ticket' => $result['ticket'],
            ];
        });
    }

    public function cancel(Request $request, AiAgentService $agent): JsonResponse
    {
        $data = $request->validate([
            'proposal_id' => ['required', 'uuid'],
        ]);

        return $this->respond(function () use ($agent, $request, $data) {
            $agent->cancel($request->user(), $data['proposal_id']);

            return [
                'reply' => 'Cancelled. Nothing was created.',
            ];
        });
    }

    public function refine(Request $request, OpenAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        return $this->respond(function () use ($ai, $data) {
            return [
                'text' => $ai->refineNote($data['text']),
            ];
        });
    }

    public function paraphrase(Request $request, OpenAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        return $this->respond(function () use ($ai, $data) {
            return [
                'text' => $ai->paraphraseNote($data['text']),
            ];
        });
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                ...$callback(),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'AI automation is temporarily unavailable.',
            ], 500);
        }
    }
}
