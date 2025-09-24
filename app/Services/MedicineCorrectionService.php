<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MedicineCorrectionService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->apiUrl = config('services.gemini.api_url');
    }

    /**
     * Correct medicine name using Gemini API
     *
     * @param string $medicineName
     * @return array
     */
    public function correctMedicineName(string $medicineName): array
    {
        try {
            $prompt = $this->buildPrompt($medicineName);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl . '?key=' . $this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'topK' => 1,
                        'topP' => 0.8,
                        'maxOutputTokens' => 2048,
                    ]
                ]);

            if (!$response->successful()) {
                Log::error('Gemini API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return $this->getErrorResponse($medicineName, 'API request failed');
            }

            $responseData = $response->json();

            if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                Log::error('Invalid Gemini API response structure', ['response' => $responseData]);
                return $this->getErrorResponse($medicineName, 'Invalid API response');
            }

            $jsonResponse = $responseData['candidates'][0]['content']['parts'][0]['text'];

            // Clean the response (remove any markdown formatting)
            $jsonResponse = preg_replace('/```json\s*/', '', $jsonResponse);
            $jsonResponse = preg_replace('/```\s*$/', '', $jsonResponse);
            $jsonResponse = trim($jsonResponse);

            $decodedResponse = json_decode($jsonResponse, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to decode JSON response from Gemini', [
                    'json_error' => json_last_error_msg(),
                    'response' => $jsonResponse
                ]);
                return $this->getErrorResponse($medicineName, 'Invalid JSON response');
            }

            return $decodedResponse;

        } catch (Exception $e) {
            Log::error('Medicine correction service error', [
                'error' => $e->getMessage(),
                'medicine_name' => $medicineName
            ]);

            return $this->getErrorResponse($medicineName, 'Service error');
        }
    }

    /**
     * Build the prompt for Gemini API
     *
     * @param string $medicineName
     * @return string
     */
    private function buildPrompt(string $medicineName): string
    {
        return "SYSTEM / CONTEXT:
You are a clinical-grade medicine name correction assistant. You have broad knowledge of commercial (brand) and generic drug names across global markets. You must NOT invent new drug names. Only suggest names you are confident exist in real life. If unsure, return \"unknown\".

TASK:
- Receive a user-entered medicine name (may include typos, missing/extra letters, wrong case, transliteration, numbers/strengths, or partial names).
- Normalize the input (trim spaces, remove extraneous punctuation except hyphens that are part of names, remove strength/units like \"500 mg\" unless part of the brand).
- Determine whether the input:
  - matches a known commercial/generic drug exactly (status \"valid\"),
  - is close to one or more known drugs (status \"similar\"),
  - or is not recognizable / too ambiguous (status \"unknown\").

OUTPUT FORMAT (strict JSON ONLY):
Respond ONLY with a JSON object exactly matching this schema. Do not include any human language outside the JSON.

{
  \"status\": \"valid\" | \"similar\" | \"unknown\",
  \"input\": \"<original_user_input>\",
  \"normalized_input\": \"<normalized version used for matching>\",
  \"corrections\": [
    {
      \"name\": \"<suggested drug brand or generic name>\",
      \"type\": \"brand\" | \"generic\" | \"other\",
      \"canonical_name\": \"<if suggestion is brand, provide generic active ingredient if known; else null>\",
      \"similarity\": <integer 0-100>,
      \"confidence\": <float 0.0-1.0>,
      \"match_reason\": \"<short reason: e.g. 'typo', 'missing initial letter', 'common transliteration', 'substring match'>\",
      \"notes\": \"<optional short note: e.g. 'common OTC analgesic'>\"
    }
  ],
  \"max_suggestions_returned\": <integer>,
  \"requires_confirmation\": <boolean>,
  \"auto_accept\": <boolean>,
  \"explanation\": \"<optional very short machine-oriented note — only if needed>\"
}

RULES & LOGIC (apply these exactly):
1. Similarity scale:
   - similarity >= 90 → treat as almost exact (likely valid).
   - 70 <= similarity < 90 → similar (possible matches).
   - similarity < 70 → not sufficient (do not return low-similarity names unless no other options).
2. Confidence: estimate model confidence (0.0-1.0).
   - auto_accept = true only if similarity >= 92 AND confidence >= 0.95.
   - requires_confirmation = true if more than one suggestion OR similarity < 92.
3. Suggestions:
   - Return up to 5 suggestions, sorted by similarity descending.
   - For each suggestion, set `type` (brand/generic) and `canonical_name` (generic active ingredient) if known.
   - Do NOT invent names. If you cannot confidently map to known drug(s), return `\"status\": \"unknown\"` and an empty `corrections` array.
4. Normalization:
   - Strip strength info (e.g., \"Paracetamol 500 mg\" → \"Paracetamol\") for matching, but keep original `input` field unchanged.
   - Handle case-insensitive matching and common transliterations (e.g., Arabic transliteration to English).
5. Language:
   - If input is in Arabic or transliterated Arabic, try to map to the internationally recognized brand/generic names and return suggestions in the Latin script (English), but preserve Arabic if that is the common commercial name.
6. Ambiguity:
   - If the input could be a brand name or part of a compound (e.g., combination drugs), include those possibilities with clear `match_reason`.
7. Safety:
   - Never recommend an alternative that is unsafe or invents a new formulation. If unsure about therapeutic equivalence, do NOT suggest generics as equivalents — only provide names.
8. Response strictness:
   - Output JSON only. No commentary. No apologies. No chain-of-thought.

PLACEHOLDER:
User input to check: \"{$medicineName}\"

END.";
    }

    /**
     * Get error response when API fails
     *
     * @param string $medicineName
     * @param string $reason
     * @return array
     */
    private function getErrorResponse(string $medicineName, string $reason): array
    {
        return [
            'status' => 'unknown',
            'input' => $medicineName,
            'normalized_input' => strtolower(trim($medicineName)),
            'corrections' => [],
            'max_suggestions_returned' => 0,
            'requires_confirmation' => true,
            'auto_accept' => false,
            'explanation' => $reason
        ];
    }

    /**
     * Get autocomplete suggestions (simplified version for real-time suggestions)
     *
     * @param string $medicineName
     * @return array
     */
    public function getAutocompleteSuggestions(string $medicineName): array
    {
        if (strlen(trim($medicineName)) < 2) {
            return [
                'status' => 'unknown',
                'input' => $medicineName,
                'normalized_input' => strtolower(trim($medicineName)),
                'corrections' => [],
                'max_suggestions_returned' => 0,
                'requires_confirmation' => false,
                'auto_accept' => false,
                'explanation' => 'Input too short'
            ];
        }

        $result = $this->correctMedicineName($medicineName);

        // Limit to 3 suggestions for autocomplete
        if (isset($result['corrections']) && count($result['corrections']) > 3) {
            $result['corrections'] = array_slice($result['corrections'], 0, 3);
            $result['max_suggestions_returned'] = 3;
        }

        return $result;
    }
}
