<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiService
{
    public function __construct(private readonly AiAgentService $agent) {}

    public function enabled(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function chatCompletion(array $payload): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('OpenAI is not configured. Add OPENAI_API_KEY to the environment.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
                ->withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->timeout(60)
                ->post('/chat/completions', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message')
                ?: 'The AI service could not complete this request.';

            throw new RuntimeException($message, previous: $exception);
        }

        if (! is_array($response)) {
            throw new RuntimeException('The AI service returned an unexpected response.');
        }

        return $response;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, float $temperature = 0.4): string
    {
        $response = $this->chatCompletion([
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => $temperature,
            'messages' => $messages,
        ]);

        $content = trim((string) data_get($response, 'choices.0.message.content', ''));
        if ($content === '') {
            throw new RuntimeException('The AI service returned an empty response.');
        }

        return $content;
    }

    public function refineNote(string $text): string
    {
        return $this->chat([
            [
                'role' => 'system',
                'content' => 'You refine ICT operations notes for PlantGrid, a manufacturing technology portfolio demo. '
                    .'Improve clarity, grammar and structure. Keep factual meaning. Do not invent plant events, people, assets or tickets. '
                    .'Return only the refined note text with no preamble.',
            ],
            [
                'role' => 'user',
                'content' => $text,
            ],
        ], 0.2);
    }

    public function paraphraseNote(string $text): string
    {
        return $this->chat([
            [
                'role' => 'system',
                'content' => 'You paraphrase ICT operations notes for PlantGrid. '
                    .'Rewrite in clear professional language while preserving meaning. Do not add new facts. '
                    .'Return only the paraphrased note text with no preamble.',
            ],
            [
                'role' => 'user',
                'content' => $text,
            ],
        ], 0.5);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, proposal: ?array<string, mixed>}
     */
    public function assistantTurn(string $message, array $history = [], ?User $user = null, ?string $liveContext = null): array
    {
        $user ??= auth()->user();
        if (! $user) {
            throw new RuntimeException('Sign in to use the assistant.');
        }

        $context = trim((string) ($liveContext ?? app(AiContextService::class)->snapshot($user, $message)));
        $tools = $this->agent->toolsFor($user);
        $toolNames = collect($tools)->map(fn ($tool) => data_get($tool, 'function.name'))->filter()->values()->all();

        $system = [
            'role' => 'system',
            'content' => implode("\n", [
                'You are PlantGrid Assistant for a portfolio demonstration of manufacturing ICT operations at fictional Northridge Processing.',
                'Answer from the LIVE DEMO SNAPSHOT and LOOKUP HITS first for cases, tickets, alerts, assets, network devices, servers, people/contacts, maintenance, or status questions.',
                'Asset tags like PG-NET-0006 and hostnames like PLT-AP-PACK are in the register — use those rows directly.',
                'For contact questions, use the People directory: name, email, role, department. If no phone exists, say phone is not stored in the demo.',
                'Lead with a direct answer (yes/no/status/counts/contact), then cite specific people, tags, hostnames, or ticket numbers from the snapshot.',
                'Keep replies short: ideally 2–5 short sentences or a compact bullet list.',
                'Remind once, briefly, that sample data is simulated when discussing operational status.',
                'Do not claim to control real machinery or live networks.',
                'Do not invent ticket numbers.',
                'Available action tools for this user: '.( $toolNames === [] ? 'none' : implode(', ', $toolNames)).'.',
                'When the user asks to create/open/log a case, call propose_create_case if available.',
                'When the user asks to take/accept/claim ownership of a case, call propose_take_ownership if available.',
                'When the user asks to close a case as duplicate, call propose_close_duplicate if available.',
                'Never claim an action completed until the user confirms the proposal card.',
                '',
                $context,
                '',
                $this->agent->catalogSnippet(),
            ]),
        ];

        $messages = [$system];
        foreach (array_slice($history, -8) as $item) {
            if (! in_array($item['role'] ?? '', ['user', 'assistant'], true)) {
                continue;
            }
            $content = trim((string) ($item['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $messages[] = [
                'role' => $item['role'],
                'content' => mb_substr($content, 0, 2000),
            ];
        }
        $messages[] = [
            'role' => 'user',
            'content' => mb_substr(trim($message), 0, 2000),
        ];

        $payload = [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => 0.2,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = $this->chatCompletion($payload);
        $assistantMessage = data_get($response, 'choices.0.message');
        if (! is_array($assistantMessage)) {
            throw new RuntimeException('The AI service returned an empty response.');
        }

        if (! empty($assistantMessage['tool_calls']) && is_array($assistantMessage['tool_calls'])) {
            return $this->agent->handleToolCalls($user, $assistantMessage, $message);
        }

        $content = trim((string) ($assistantMessage['content'] ?? ''));
        if ($content === '') {
            throw new RuntimeException('The AI service returned an empty response.');
        }

        return [
            'reply' => $content,
            'proposal' => null,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function assistantReply(string $message, array $history = [], ?string $liveContext = null): string
    {
        return $this->assistantTurn($message, $history, auth()->user(), $liveContext)['reply'];
    }
}
