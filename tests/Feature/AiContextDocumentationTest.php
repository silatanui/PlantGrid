<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiContextDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_lists_documentation_guides_and_lookup_for_docs_questions(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $snapshot = app(AiContextService::class)->snapshot($user, 'Is there any docs?');

        $this->assertStringContainsString('Documentation (/docs)', $snapshot);
        $this->assertStringContainsString('Getting started with PlantGrid', $snapshot);
        $this->assertStringContainsString('/docs/getting-started', $snapshot);
        $this->assertStringContainsString('LOOKUP HITS for the current question:', $snapshot);
        $this->assertStringContainsString('Documentation is available in the Documentation module', $snapshot);
        $this->assertStringContainsString('these ARE the product docs', $snapshot);
    }
}
