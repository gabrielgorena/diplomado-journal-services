<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class ContentSuggestionService
{
    public function getSuggestions(string $topic): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are an assistant specialized in generating journalistic content suggestions. Always reply in the language this is written in: {$topic}"
                ],
                [
                    'role' => 'user',
                    'content' => "Topic: {$topic}\n\nYour task is to analyze whether the above message is a valid journalistic topic.
                    If it is, return exactly 3 useful, creative, and specific journalistic content suggestions in the following JSON format:

                    [
                      {
                        \"title\": \"Short title here\",
                        \"content\": \"Detailed explanation here\"
                      },
                      ...
                    ]

                    If the message is not a valid topic, respond with a single JSON object like this:

                    {
                      \"error\": \"I can only assist with content suggestions for journalistic topics.\"
                    }

                    Respond only with JSON. No explanations."
                ],
            ],
        ]);

        $content = $response['choices'][0]['message']['content'];
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid response from AI.');
        }

        return $decoded;
    }
}
