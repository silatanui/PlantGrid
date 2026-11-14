<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DocumentationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentationCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_novice_guides_are_listed_and_openable(): void
    {
        $slugs = [
            'getting-started',
            'demo-roles',
            'find-your-way',
            'report-an-issue',
            'my-cases',
            'messages-basics',
            'search-people-assets',
            'plant-map-basics',
            'monitoring-basics',
            'glossary',
        ];

        foreach ($slugs as $slug) {
            $this->assertNotNull(DocumentationCatalog::find($slug), $slug.' missing from catalog');
        }

        $this->assertTrue(
            DocumentationCatalog::search('getting started', 5)->contains(fn (array $doc) => $doc['slug'] === 'getting-started')
        );

        $this->seed();

        $user = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $this->actingAs($user)
            ->get(route('docs.index'))
            ->assertOk()
            ->assertSee('Getting started with PlantGrid')
            ->assertSee('Glossary of common PlantGrid words');

        $this->actingAs($user)
            ->get(route('docs.show', 'getting-started'))
            ->assertOk()
            ->assertSee('What PlantGrid is');
    }
}
