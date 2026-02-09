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
     * @return string|WP_Error The generated text on success, or WP_Error on failure.
     */
    public static function generate($prompt)
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
}
