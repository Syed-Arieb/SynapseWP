<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SynapseWP_Images
 *
 * Handles image optimization features including alt-text and caption generation.
 */
class SynapseWP_Images
{

    /**
     * Initialize the class and set up hooks.
     */
    public function __construct()
    {
        // Hook into attachment upload if auto-generation is enabled
        if (get_option('sma_auto_alt_text', false)) {
            add_action('add_attachment', [$this, 'auto_generate_alt_text']);
        }

        // Register REST API endpoints for manual alt-text generation
        add_action('rest_api_init', [$this, 'register_endpoints']);

        // Add "Generate Alt Text" button to media library
        add_filter('attachment_fields_to_edit', [$this, 'add_alt_text_button'], 10, 2);
    }

    /**
     * Register REST API endpoints for image optimization.
     */
    public function register_endpoints()
    {
        register_rest_route('synapsewp/v1', '/generate-alt-text', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generate_alt_text'],
            'permission_callback' => function () {
                return current_user_can('upload_files');
            },
        ]);

        register_rest_route('synapsewp/v1', '/bulk-alt-text', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_bulk_alt_text'],
            'permission_callback' => function () {
                return current_user_can('upload_files');
            },
        ]);
    }

    /**
     * Automatically generate alt text when an image is uploaded.
     *
     * @param int $attachment_id The attachment ID.
     */
    public function auto_generate_alt_text($attachment_id)
    {
        // Only process images
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }

        // Check if alt text already exists
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($existing_alt)) {
            return; // Don't override existing alt text
        }

        $this->generate_and_save_alt_text($attachment_id);
    }

    /**
     * Handle REST API request for alt-text generation.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_generate_alt_text($request)
    {
        $params = $request->get_json_params();
        $attachment_id = intval($params['attachment_id'] ?? 0);

        if (empty($attachment_id)) {
            return new WP_Error('missing_attachment', __('No attachment ID provided.', 'synapsewp'), ['status' => 400]);
        }

        if (!wp_attachment_is_image($attachment_id)) {
            return new WP_Error('invalid_attachment', __('The attachment is not an image.', 'synapsewp'), ['status' => 400]);
        }

        $result = $this->generate_and_save_alt_text($attachment_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'data' => [
                'alt_text' => $result['alt_text'],
                'caption' => $result['caption'],
                'message' => __('Alt text generated successfully.', 'synapsewp')
            ]
        ]);
    }

    /**
     * Handle bulk alt-text generation.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_bulk_alt_text($request)
    {
        $params = $request->get_json_params();
        $limit = intval($params['limit'] ?? 10);

        // Get images without alt text
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                ]
            ]
        ];

        $images = get_posts($args);
        $processed = 0;
        $errors = [];

        foreach ($images as $image) {
            $result = $this->generate_and_save_alt_text($image->ID);

            if (is_wp_error($result)) {
                $errors[] = [
                    'id' => $image->ID,
                    'message' => $result->get_error_message()
                ];
            } else {
                $processed++;
            }
        }

        return rest_ensure_response([
            'data' => [
                'processed' => $processed,
                'total_found' => count($images),
                'errors' => $errors,
                'message' => sprintf(__('Processed %d images.', 'synapsewp'), $processed)
            ]
        ]);
    }

    /**
     * Generate and save alt text for an attachment.
     *
     * @param int $attachment_id The attachment ID.
     * @return array|WP_Error Array with alt_text and caption on success, or WP_Error on failure.
     */
    private function generate_and_save_alt_text($attachment_id)
    {
        $image_url = wp_get_attachment_url($attachment_id);

        if (!$image_url) {
            return new WP_Error('invalid_url', __('Could not get image URL.', 'synapsewp'));
        }

        // Generate alt text using Gemini's vision API
        $prompt = "Describe this image in detail for accessibility purposes. 
        Rules:
        1. Provide exactly ONE concise alt text (50-125 characters) for screen readers.
        2. Provide exactly ONE slightly longer caption suitable for display (1-2 sentences).
        3. Return strictly valid JSON in this exact format: {\"alt_text\": \"...\", \"caption\": \"...\"}
        4. Do NOT provide options or conversational filler.";

        $raw_response = SynapseWP_API::generate_from_image($image_url, $prompt);

        if (is_wp_error($raw_response)) {
            return $raw_response;
        }

        $data = SynapseWP_API::parse_json_response($raw_response);

        // Fallback: use raw output as alt text if parsing fails
        if (is_wp_error($data)) {
            $alt_text = wp_trim_words($raw_response, 15);
            $caption = wp_trim_words($raw_response, 25);
            $data = [
                'alt_text' => $alt_text,
                'caption' => $caption
            ];
        }

        // Save alt text
        if (!empty($data['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($data['alt_text']));
        }

        // Optionally save caption
        if (!empty($data['caption'])) {
            wp_update_post([
                'ID' => $attachment_id,
                'post_excerpt' => sanitize_text_field($data['caption'])
            ]);
        }

        return $data;
    }

    /**
     * Add "Generate Alt Text" button to media edit screen.
     *
     * @param array $form_fields Array of attachment form fields.
     * @param WP_Post $post The attachment post object.
     * @return array Modified form fields.
     */
    public function add_alt_text_button($form_fields, $post)
    {
        if (!wp_attachment_is_image($post->ID)) {
            return $form_fields;
        }

        $form_fields['synapsewp_alt_button'] = [
            'label' => __('AI Alt Text', 'synapsewp'),
            'input' => 'html',
            'html' => '<button type="button" class="button synapsewp-generate-alt" data-attachment-id="' . esc_attr($post->ID) . '">' .
                __('Generate with AI', 'synapsewp') .
                '</button>' .
                '<p class="description">' . __('Use AI to generate descriptive alt text for this image.', 'synapsewp') . '</p>',
        ];

        return $form_fields;
    }
}
