<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SynapseWP_Writer
 *
 * Handles content generation and auto-categorization features using the SynapseWP AI API.
 */
class SynapseWP_Writer
{

    /**
     * Initialize the class and set up hooks.
     */
    public function __construct()
    {
        // Register REST API endpoints.
        add_action('rest_api_init', [$this, 'register_endpoints']);

        // Hook into post publication for auto-categorization.
        add_action('publish_post', [$this, 'auto_categorize'], 10, 2);
    }

    /**
     * Register custom REST API routes for the writing assistant.
     */
    public function register_endpoints()
    {
        // Existing expand endpoint
        register_rest_route('synapsewp/v1', '/expand', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_expansion'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // Content rewriting endpoints
        register_rest_route('synapsewp/v1', '/summarize', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_summarize'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('synapsewp/v1', '/paraphrase', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_paraphrase'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('synapsewp/v1', '/improve', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_improve'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('synapsewp/v1', '/simplify', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_simplify'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // Translation endpoint
        register_rest_route('synapsewp/v1', '/translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_translate'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // SEO endpoints
        register_rest_route('synapsewp/v1', '/generate-meta', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generate_meta'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // FAQ and template endpoints
        register_rest_route('synapsewp/v1', '/generate-faq', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generate_faq'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('synapsewp/v1', '/bullet-summary', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_bullet_summary'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    /**
     * Callback API handler for text expansion.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    /**
     * Callback API handler for text expansion.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_expansion($request)
    {
        $params = $request->get_json_params();
        $text = sanitize_text_field($params['text'] ?? '');
        $mode = sanitize_text_field($params['mode'] ?? 'answer');
        $history = isset($params['history']) ? $params['history'] : [];

        if (empty($text)) {
            return new WP_Error('missing_text', __('No text provided for expansion.', 'synapsewp'), ['status' => 400]);
        }

        // --- WRITER MODE ---
        if ($mode === 'writer') {
            // Prompt for JSON response with title and content
            $prompt = "You are a professional blog writer. Writes a comprehensive article section based on this topic: '{$text}'. 
            Also generate an engaging, SEO-optimized title for this post.
            
            Return strictly valid JSON in the following format:
            {
                \"title\": \"Your Title Here\",
                \"content\": \"Your HTML formatted content here (use <p>, <h2>, <ul> etc.)\"
            }
            Do not include ```json fences or any other text.";

            // Writer mode currently doesn't support full history context to keep the "one-shot article" focus,
            // but we could add it if requested. For now, it stays as is.
            $raw_response = SynapseWP_API::generate($prompt, 'json');

            if (is_wp_error($raw_response)) {
                return $raw_response;
            }

            $data = SynapseWP_API::parse_json_response($raw_response);

            if (is_wp_error($data)) {
                // Fallback: return raw output as content
                return rest_ensure_response([
                    'data' => [
                        'title' => '',
                        'content' => $raw_response
                    ]
                ]);
            }

            return rest_ensure_response(['data' => $data]);
        }

        // --- CHAT / ANSWER MODE ---

        // Convert history to string context if API doesn't support chat objects directly (Gemini 1.5/2.5 often prefers simple string for REST unless using specific chat endpoints).
        // For simplicity and compatibility with our existing `generate` method which expects a string prompt,
        // we will append the history as specific context.

        $context = "";
        if (!empty($history) && is_array($history)) {
            $context = "Previous Conversation History:\n";
            foreach ($history as $msg) {
                $role = isset($msg['role']) && $msg['role'] === 'user' ? 'User' : 'AI';
                $content = isset($msg['parts'][0]['text']) ? $msg['parts'][0]['text'] : '';
                if ($content) {
                    $context .= "{$role}: {$content}\n";
                }
            }
            $context .= "\nCurrent Request:\n";
        }

        $prompt = "You are a helpful WordPress AI assistant. Respond professionally to the user.\n" . $context . $text;

        $output = SynapseWP_API::generate($prompt);

        if (is_wp_error($output)) {
            return $output;
        }

        return rest_ensure_response(['data' => ['content' => $output]]);
    }

    /**
     * Automatically categorize posts upon publication.
     *
     * @param int     $post_id The post ID.
     * @param WP_Post $post    The post object.
     */
    public function auto_categorize($post_id, $post)
    {
        // Prevent running on autosave or if it's not a standard post.
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || $post->post_type !== 'post') {
            return;
        }

        // Prevent infinite loops by unhooking safely.
        remove_action('publish_post', [$this, 'auto_categorize'], 10);

        // Prepare the prompt using a snippet of the content.
        $content_snippet = wp_trim_words($post->post_content, 100);
        if (empty($content_snippet)) {
            return; // Initial empty post, skip.
        }

        $max_cats = get_option('sma_max_categories', 3);

        // Enhanced prompt for smarter categorization
        $prompt = "Analyze the following content and suggest exactly {$max_cats} relevant WordPress categories. 
        Return strictly valid JSON: {\"categories\": [\"Cat1\", \"Cat2\", \"Cat3\"]}
        Rules:
        1. Do NOT use generic terms like 'Uncategorized', 'General', or 'Updates'. 
        2. Be specific to the topic.
        
        Content: " . $content_snippet;

        $raw_response = SynapseWP_API::generate($prompt, 'json');

        if (!is_wp_error($raw_response)) {
            $data = SynapseWP_API::parse_json_response($raw_response);
            if (!is_wp_error($data) && isset($data['categories']) && is_array($data['categories'])) {
                $category_list = implode(',', $data['categories']);
                $this->assign_categories($post_id, $category_list, $max_cats);
            }
        }

        // Re-hook in case it's needed later in the same execution (unlikely but good practice).
        add_action('publish_post', [$this, 'auto_categorize'], 10, 2);
    }

    /**
     * Assign categories to a post, creating them if they don't exist.
     *
     * @param int    $post_id     The post ID.
     * @param string $category_list Comma-separated list of category names.
     * @param int    $limit       Maximum number of categories to assign.
     */
    private function assign_categories($post_id, $category_list, $limit = 3)
    {
        $names = explode(',', $category_list);

        // Enforce the limit
        if (count($names) > $limit) {
            $names = array_slice($names, 0, $limit);
        }

        $ids = [];

        foreach ($names as $name) {
            $name = trim($name);
            $name = rtrim($name, '.'); // Remove trailing docs

            if (empty($name)) {
                continue;
            }

            // Check if the term exists.
            $term = term_exists($name, 'category');

            // If not, create it.
            if (!$term) {
                $term = wp_insert_term($name, 'category');
            }

            // Collect valid term IDs.
            if (!is_wp_error($term)) {
                $term_id = is_array($term) ? $term['term_id'] : $term;
                $ids[] = (int) $term_id;
            }
        }

        // Assign the categories to the post.
        if (!empty($ids)) {
            // Get current categories to check for "Uncategorized"
            $current_cats = wp_get_post_categories($post_id);

            // "Uncategorized" usually has ID 1, but we should check by name to be sure or just assume default default.
            // Safest way is to remove default category if we are adding new valid ones.
            $default_category = get_option('default_category');

            // Add new IDs to the current list
            $new_cats = array_unique(array_merge($current_cats, $ids));

            // Remove default category if it exists in the list AND we have other categories
            if (count($new_cats) > 1 && in_array($default_category, $new_cats)) {
                $new_cats = array_diff($new_cats, array($default_category));
            }

            wp_set_post_categories($post_id, $new_cats, false);
        }
    }

    /**
     * Handle content summarization.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_summarize($request)
    {
        $params = $request->get_json_params();
        $text = wp_kses_post($params['text'] ?? '');
        $length = sanitize_text_field($params['length'] ?? 'medium'); // short, medium, long

        if (empty($text)) {
            return new WP_Error('missing_text', __('No text provided for summarization.', 'synapsewp'), ['status' => 400]);
        }

        $length_instructions = [
            'short' => 'in 1-2 sentences',
            'medium' => 'in 2-3 paragraphs',
            'long' => 'in a detailed summary'
        ];

        $instruction = $length_instructions[$length] ?? $length_instructions['medium'];
        $prompt = "Summarize the following content {$instruction}. 
        Rules:
        1. Focus on the key points and main ideas.
        2. Provide exactly ONE version of the summary. 
        3. Do NOT provide options or alternative versions.
        4. Do NOT include any conversational preamble or postamble.
        
        Content:
        {$text}";

        $output = SynapseWP_API::generate($prompt);

        if (is_wp_error($output)) {
            return $output;
        }

        return rest_ensure_response(['data' => ['content' => $output]]);
    }

    /**
     * Handle content paraphrasing.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_paraphrase($request)
    {
        $params = $request->get_json_params();
        $text = wp_kses_post($params['text'] ?? '');
        $tone = sanitize_text_field($params['tone'] ?? 'professional'); // professional, casual, academic

        if (empty($text)) {
            return new WP_Error('missing_text', __('No text provided for paraphrasing.', 'synapsewp'), ['status' => 400]);
        }

        $tone_instructions = [
            'professional' => 'in a professional and business-appropriate style',
            'casual' => 'in a casual and conversational style',
            'academic' => 'in a formal and academic style'
        ];

        $instruction = $tone_instructions[$tone] ?? $tone_instructions['professional'];
        $prompt = "Rewrite the following content {$instruction}. 
        Rules:
        1. Maintain the same meaning but use different words and sentence structures.
        2. Provide exactly ONE definitive version of the rewritten text.
        3. Do NOT provide options, choices, or comparisons.
        4. Do NOT include any conversational preamble or postamble.
        
        Content:
        {$text}";

        $output = SynapseWP_API::generate($prompt);

        if (is_wp_error($output)) {
            return $output;
        }

        return rest_ensure_response(['data' => ['content' => $output]]);
    }

    /**
     * Handle content improvement.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_improve($request)
    {
        $params = $request->get_json_params();
        $text = wp_kses_post($params['text'] ?? '');

        if (empty($text)) {
            return new WP_Error('missing_text', __('No text provided for improvement.', 'synapsewp'), ['status' => 400]);
        }

        $prompt = "Improve the following content by fixing grammar, enhancing clarity, improving flow, and making it more engaging. 
        Rules:
        1. Maintain the original meaning and voice.
        2. Provide exactly ONE definitive version of the improved text.
        3. Do NOT provide multiple options or explanations of changes.
        4. Do NOT include any conversational preamble or postamble.
        
        Content:
        {$text}";

        $output = SynapseWP_API::generate($prompt);

        if (is_wp_error($output)) {
            return $output;
        }

        return rest_ensure_response(['data' => ['content' => $output]]);
    }

    /**
     * Handle content simplification.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_simplify($request)
    {
        $params = $request->get_json_params();
        $text = wp_kses_post($params['text'] ?? '');

        if (empty($text)) {
            return new WP_Error('missing_text', __('No text provided for simplification.', 'synapsewp'), ['status' => 400]);
        }

        $prompt = "Make the following content more concise and easier to read. 
        Rules:
        1. Remove unnecessary words and simplify complex sentences.
        2. Maintain the key message.
        3. Provide exactly ONE definitive version of the simplified text.
        4. Do NOT provide multiple options or versions.
        5. Do NOT include any conversational preamble or postamble.
        
        Content:
        {$text}";

        $output = SynapseWP_API::generate($prompt);

        if (is_wp_error($output)) {
            return $output;
        }

        return rest_ensure_response(['data' => ['content' => $output]]);
    }

    /**
     * Handle content translation.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_translate($request)
    {
        $params = $request->get_json_params();
        $text = wp_kses_post($params['text'] ?? '');
        $target_language = sanitize_text_field($params['target_language'] ?? '');

        if (empty($text)) {
            return new WP_Error('missing_text', __('No text provided for translation.', 'synapsewp'), ['status' => 400]);
        }

        if (empty($target_language)) {
            return new WP_Error('missing_language', __('No target language specified.', 'synapsewp'), ['status' => 400]);
        }

        $prompt = "Translate the following content to {$target_language}. 
        Rules:
        1. Maintain the formatting, tone, and structure.
        2. Provide exactly ONE definitive translation.
        3. Do NOT provide options or alternative phrasings.
        4. Do NOT include any conversational preamble or postamble.
        
        Content:
        {$text}";

        $output = SynapseWP_API::generate($prompt);

        if (is_wp_error($output)) {
            return $output;
        }

        return rest_ensure_response(['data' => ['content' => $output]]);
    }

    /**
     * Handle SEO meta description generation.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_generate_meta($request)
    {
        $params = $request->get_json_params();
        $content = wp_kses_post($params['content'] ?? '');
        $title = sanitize_text_field($params['title'] ?? '');

        if (empty($content)) {
            return new WP_Error('missing_content', __('No content provided for meta generation.', 'synapsewp'), ['status' => 400]);
        }

        $title_context = !empty($title) ? "Post Title: {$title}\n\n" : '';

        $prompt = "{$title_context}Based on the following content, generate:
1. An SEO-optimized meta description (150-160 characters)
2. 3-5 focus keywords
3. A brief SEO score and 2-3 improvement suggestions

Return strictly valid JSON in this exact format:
{
    \"meta_description\": \"Your meta description here\",
    \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],
    \"seo_score\": \"Good/Average/Needs Work\",
    \"suggestions\": [\"suggestion1\", \"suggestion2\"]
}

Content:
{$content}";

        $raw_response = SynapseWP_API::generate($prompt, 'json');

        if (is_wp_error($raw_response)) {
            return $raw_response;
        }

        $data = SynapseWP_API::parse_json_response($raw_response);

        if (is_wp_error($data)) {
            // Fallback: return raw output
            return rest_ensure_response(['data' => ['raw' => $raw_response]]);
        }

        return rest_ensure_response(['data' => $data]);
    }

    /**
     * Handle FAQ generation.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_generate_faq($request)
    {
        $params = $request->get_json_params();
        $content = wp_kses_post($params['content'] ?? '');

        if (empty($content)) {
            return new WP_Error('missing_content', __('No content provided for FAQ generation.', 'synapsewp'), ['status' => 400]);
        }

        $prompt = "Based on the following content, generate 5-7 frequently asked questions (FAQs) with answers. 
        Rules:
        1. Format as HTML with <h3> for questions and <p> for answers.
        2. Provide exactly ONE definitive set of FAQs.
        3. Do NOT provide options or conversational filler.
        
        Content:
        {$content}";

        $output = SynapseWP_API::generate($prompt);

        if (is_wp_error($output)) {
            return $output;
        }

        return rest_ensure_response(['data' => ['content' => $output]]);
    }

    /**
     * Handle bullet-point summary generation.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_bullet_summary($request)
    {
        $params = $request->get_json_params();
        $content = wp_kses_post($params['content'] ?? '');

        if (empty($content)) {
            return new WP_Error('missing_content', __('No content provided for bullet summary.', 'synapsewp'), ['status' => 400]);
        }

        $prompt = "Extract the key points from the following content and present them as a bullet-point list. 
        Rules:
        1. Use HTML <ul> and <li> tags. 
        2. Limit to 5-8 main points.
        3. Provide exactly ONE definitive list.
        4. Do NOT provide options or conversational filler.
        
        Content:
        {$content}";

        $output = SynapseWP_API::generate($prompt);

        if (is_wp_error($output)) {
            return $output;
        }

        return rest_ensure_response(['data' => ['content' => $output]]);
    }
}
