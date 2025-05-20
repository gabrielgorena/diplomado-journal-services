<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception; // Para manejo de errores genéricos

class ContentSuggestionService
{
    private Client $httpClient;
    private string $geminiApiKey;
    private string $geminiApiUrl;

    public function __construct()
    {
        $this->httpClient = new Client();
        $this->geminiApiKey = getenv('GEMINI_API_KEY'); // Asegúrate de tener esta variable de entorno configurada

        if (empty($this->geminiApiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY environment variable is not set.');
        }

        // Usando el modelo Gemini 2.0 Flash
        $model = 'gemini-2.0-flash';
        $this->geminiApiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->geminiApiKey}";
    }

    public function getSuggestions(string $topic): array
    {
        $prompt = "You are an assistant specialized in generating journalistic content suggestions.
The topic provided is: '{$topic}'.
The language of the topic '{$topic}' suggests the desired output language. Please respond *only* in that language.

Your task is to analyze whether the provided topic is a valid journalistic topic.
If it is, return exactly 3 useful, creative, and specific journalistic content suggestions in the following JSON format:

[
  {
    \"title\": \"Short title here\",
    \"content\": \"Detailed explanation here\"
  },
  {
    \"title\": \"Another short title\",
    \"content\": \"Another detailed explanation\"
  },
  {
    \"title\": \"Third short title\",
    \"content\": \"Third detailed explanation\"
  }
]

If the message is not a valid topic, respond with a single JSON object like this:

{
  \"error\": \"I can only assist with content suggestions for journalistic topics.\"
}

Respond only with the JSON structure. Do not include any explanatory text before or after the JSON.
Do not use markdown like ```json ``` to wrap the JSON output.
The entire response must be a valid JSON.
";

        try {
            $response = $this->httpClient->post($this->geminiApiUrl, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                    ]
                ]
            ]);

            $responseBodyString = (string) $response->getBody();
            $decoded = json_decode($responseBodyString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Gemini API response is not valid JSON. Raw response: " . $responseBodyString);
                throw new \RuntimeException('Invalid JSON response from Gemini API. JSON decode error: ' . json_last_error_msg());
            }

            // Manejar la estructura de la respuesta de Gemini
            if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                $jsonOutputString = $decoded['candidates'][0]['content']['parts'][0]['text'];
                $finalDecoded = json_decode($jsonOutputString, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("The 'text' part of the Gemini response is not valid JSON: " . $jsonOutputString);
                    throw new \RuntimeException('The content from Gemini is not valid JSON.');
                }
                return $finalDecoded;
            } elseif (isset($decoded['error'])) {
                return $decoded;
            } elseif (isset($decoded['promptFeedback']['blockReason'])) {
                $blockReason = $decoded['promptFeedback']['blockReason'];
                $safetyRatings = isset($decoded['promptFeedback']['safetyRatings']) ? json_encode($decoded['promptFeedback']['safetyRatings']) : 'N/A';
                throw new \RuntimeException("Gemini API blocked the prompt. Reason: {$blockReason}. Safety Ratings: {$safetyRatings}");
            } elseif (isset($decoded['candidates'][0]['finishReason']) && $decoded['candidates'][0]['finishReason'] === 'SAFETY') {
                $safetyRatings = isset($decoded['candidates'][0]['safetyRatings']) ? json_encode($decoded['candidates'][0]['safetyRatings']) : 'N/A';
                throw new \RuntimeException("Gemini API blocked the response due to safety concerns. Finish Reason: SAFETY. Safety Ratings: {$safetyRatings}");
            } else {
                error_log("Gemini API response format is unexpected. Decoded: " . json_encode($decoded));
                throw new \RuntimeException('Unexpected response format from Gemini API.');
            }

        } catch (RequestException $e) {
            $errorMessage = 'Error connecting to Gemini API: ' . $e->getMessage();
            if ($e->hasResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                $errorMessage .= ' | Response: ' . $responseBody;
                $errorDetails = json_decode($responseBody, true);
                if (isset($errorDetails['error']['message'])) {
                    $errorMessage .= ' | API Error: ' . $errorDetails['error']['message'];
                }
            }
            error_log($errorMessage);
            throw new \RuntimeException('Failed to get suggestions from Gemini API. ' . $errorMessage, $e->getCode(), $e);
        } catch (Exception $e) {
            error_log('General error in ContentSuggestionService: ' . $e->getMessage());
            throw $e;
        }
    }
}

