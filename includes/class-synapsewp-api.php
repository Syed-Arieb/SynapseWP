<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SynapseWP_API
 *
 * Handles all communications with the Google Gemini API.
 */
class SynapseWP_API
{

    /**
     * Sends a prompt to Gemini and returns the generated text.
     *
     * @param string $prompt The prompt to send to the AI.
     * @param string $response_format Optional. The response format ('text' or 'json').
     * @return string|WP_Error The generated text on success, or WP_Error on failure.
     */
    public static function generate($prompt, $response_format = 'text')
    {
        // Retrieve API Key and Model from settings.
        // Defaulting to 'gemini-2.5-flash-lite' as per user snippet provided, or fall back to recent settings default if saved.
        $api_key = get_option('sma_ai_key');
        $model = get_option('sma_ai_model', 'gemini-2.5-flash-lite');

        // Check if API key is configured.
        if (empty($api_key)) {
            return new WP_Error('missing_key', __('API Key is missing. Please configure it in the SynapseWP settings.', 'synapsewp'));
        }

        // API Endpoint URL.
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

        // Prepare the payload.
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ];

        // Add JSON mode if requested
        if ('json' === $response_format) {
            $body['generationConfig']['response_mime_type'] = 'application/json';
        }

        // Send POST request to Gemini API.
        $response = wp_remote_post($url, [
            'body' => json_encode($body),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);

        // Handle HTTP connection errors.
        if (is_wp_error($response)) {
            return $response;
        }

        // Retrieve response body and code.
        $response_code = wp_remote_retrieve_response_code($response);
        $body_content = wp_remote_retrieve_body($response);
        $data = json_decode($body_content, true);

        // Handle API errors (non-200 status or error in body).
        if ($response_code !== 200) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown API Error.', 'synapsewp');
            return new WP_Error('api_error', $error_msg);
        }

        // Validate and return the candidate text.
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        // Handle cases where no candidates are returned (e.g., safety filter).
        return new WP_Error('no_response', __('AI Error: No response generated. It might be blocked by safety settings.', 'synapsewp'));
    }

    /**
     * Sends a prompt to Gemini with custom configuration.
     *
     * @param string $prompt The prompt to send to the AI.
     * @param array $config Optional configuration array. Supports:
     *                      - temperature (float): Controls randomness (0.0-1.0)
     *                      - max_tokens (int): Maximum length of response
     *                      - response_format (string): 'text' or 'json'
     * @return string|WP_Error The generated text on success, or WP_Error on failure.
     */
    public static function generate_with_config($prompt, $config = [])
    {
        $api_key = get_option('sma_ai_key');
        $model = get_option('sma_ai_model', 'gemini-2.5-flash-lite');

        if (empty($api_key)) {
            return new WP_Error('missing_key', __('API Key is missing. Please configure it in the SynapseWP settings.', 'synapsewp'));
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

        // Build payload with optional configuration
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ];

        // Add generation config if provided
        if (!empty($config)) {
            $generation_config = [];

            if (isset($config['temperature'])) {
                $generation_config['temperature'] = floatval($config['temperature']);
            }

            if (isset($config['max_tokens'])) {
                $generation_config['maxOutputTokens'] = intval($config['max_tokens']);
            }

            if (isset($config['response_format']) && 'json' === $config['response_format']) {
                $generation_config['response_mime_type'] = 'application/json';
            }

            if (!empty($generation_config)) {
                $body['generationConfig'] = $generation_config;
            }
        }

        $response = wp_remote_post($url, [
            'body' => json_encode($body),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body_content = wp_remote_retrieve_body($response);
        $data = json_decode($body_content, true);

        if ($response_code !== 200) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown API Error.', 'synapsewp');
            return new WP_Error('api_error', $error_msg);
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        return new WP_Error('no_response', __('AI Error: No response generated. It might be blocked by safety settings.', 'synapsewp'));
    }

    /**
     * Analyzes an image and generates descriptive text.
     *
     * @param string $image_url The URL of the image to analyze.
     * @param string $prompt The prompt to guide the analysis.
     * @return string|WP_Error The generated description on success, or WP_Error on failure.
     */
    public static function generate_from_image($image_url, $prompt = 'Describe this image in detail for accessibility purposes.')
    {
        $api_key = get_option('sma_ai_key');
        $model = get_option('sma_ai_model', 'gemini-2.5-flash-lite');

        if (empty($api_key)) {
            return new WP_Error('missing_key', __('API Key is missing. Please configure it in the SynapseWP settings.', 'synapsewp'));
        }

        // Get image data
        $image_data = self::get_image_base64($image_url);
        if (is_wp_error($image_data)) {
            return $image_data;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

        // Prepare multimodal payload
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $image_data['mime_type'],
                                'data' => $image_data['base64']
                            ]
                        ],
                    ],
                ],
            ],
        ];

        $response = wp_remote_post($url, [
            'body' => json_encode($body),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body_content = wp_remote_retrieve_body($response);
        $data = json_decode($body_content, true);

        if ($response_code !== 200) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown API Error.', 'synapsewp');
            return new WP_Error('api_error', $error_msg);
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        return new WP_Error('no_response', __('AI Error: No response generated. It might be blocked by safety settings.', 'synapsewp'));
    }

    /**
     * Helper function to get base64 encoded image data from URL.
     *
     * @param string $image_url The URL of the image.
     * @return array|WP_Error Array with 'base64' and 'mime_type' on success, or WP_Error on failure.
     */
    private static function get_image_base64($image_url)
    {
        // Download image
        $response = wp_remote_get($image_url, ['timeout' => 15]);

        if (is_wp_error($response)) {
            return $response;
        }

        $image_data = wp_remote_retrieve_body($response);
        $mime_type = wp_remote_retrieve_header($response, 'content-type');

        // Validate it's an image
        if (!str_starts_with($mime_type, 'image/')) {
            return new WP_Error('invalid_image', __('The URL does not point to a valid image.', 'synapsewp'));
        }

        return [
            'base64' => base64_encode($image_data),
            'mime_type' => $mime_type
        ];
    }

    /**
     * Robustly parses a JSON response from the AI.
     *
     * @param string $json_string The raw response string.
     * @return array|WP_Error The parsed array on success, or WP_Error on failure.
     */
    public static function parse_json_response($json_string)
    {
        if (empty($json_string)) {
            return new WP_Error('empty_json', __('AI returned an empty response.', 'synapsewp'));
        }

        // 1. Try direct decoding
        $data = json_decode($json_string, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // 2. Remove potential Markdown fences
        $clean_json = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($json_string));
        $data = json_decode($clean_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // 3. Extract JSON using regex if there's preamble/postamble text
        if (preg_match('/\{.*\}/s', $json_string, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }

        return new WP_Error('json_parse_error', __('Failed to parse AI response as JSON.', 'synapsewp'), [
            'raw_response' => $json_string,
            'json_error' => json_last_error_msg()
        ]);
    }
}
