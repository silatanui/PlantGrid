<?php

namespace App\Http\Controllers;

use App\Support\DocumentationCatalog;
use Illuminate\Http\Request;

class DocsController extends Controller
{
    public function index(Request $request)
    {
        $term = trim($request->string('q')->toString());
        $docs = $term === ''
            ? DocumentationCatalog::collection()
            : DocumentationCatalog::search($term, 50);

        return view('docs.index', [
            'title' => 'Documentation',
            'docs' => $docs,
            'term' => $term,
        ]);
    }

    public function show(string $slug)
    {
        $doc = DocumentationCatalog::find($slug);
        abort_unless($doc, 404);

        $related = DocumentationCatalog::collection()
            ->reject(fn (array $item) => $item['slug'] === $slug)
            ->sortByDesc(function (array $item) use ($doc) {
                $score = 0;
                if (($item['audience'] ?? '') === ($doc['audience'] ?? '')) {
                    $score += 2;
                }
                $shared = array_intersect(
                    array_map('strtolower', $item['keywords'] ?? []),
                    array_map('strtolower', $doc['keywords'] ?? [])
                );
                $score += min(3, count($shared));

                return $score;
            })
            ->take(4)
            ->values();

        return view('docs.show', [
            'title' => $doc['title'],
            'doc' => $doc,
            'related' => $related,
        ]);
    }
}
