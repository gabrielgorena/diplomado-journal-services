<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuggestionRequest;
use App\Models\Suggestion;
use App\Services\ContentSuggestionService;
use Illuminate\Http\JsonResponse;

class SuggestionController extends Controller
{
    protected ContentSuggestionService $service;

    public function __construct(ContentSuggestionService $service)
    {
        $this->service = $service;
    }

    public function store(StoreSuggestionRequest $request): JsonResponse
    {
        $topic = $request->input('prompt');

        try {
            $data = $this->service->getSuggestions($topic);

            if (isset($data['error'])) {
                return response()->json([
                    'error' => $data['error'],
                    'prompt' => $topic,
                ], 400);
            }

            Suggestion::create([
                'topic' => $topic,
                'suggestions' => json_encode($data),
            ]);
            return response()->json([
                'suggestions' => $data,
                'prompt' => $topic,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'An unexpected error occurred.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
