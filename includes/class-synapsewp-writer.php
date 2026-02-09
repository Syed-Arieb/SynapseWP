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
        register_rest_route('synapsewp/v1', '/expand', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_expansion'],
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
    public function handle_expansion($request)
    {
        $params = $request->get_json_params();
        $text = sanitize_text_field($params['text'] ?? '');

        if (empty($text)) {
            return new WP_Error('missing_text', __('No text provided for expansion.', 'synapsewp'), ['status' => 400]);
        }

        $prompt = "Expand this idea into a professional paragraph: " . $text;

        // Call the AI API wrapper.
        $output = SynapseWP_API::generate($prompt);

        if (is_wp_error($output)) {
            return $output;
        }

        return rest_ensure_response(['generated_text' => $output]);
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
        // Increased context length for better categorization
        $content_snippet = wp_trim_words($post->post_content, 100);
        if (empty($content_snippet)) {
            return; // Initial empty post, skip.
        }

        $max_cats = get_option('sma_max_categories', 3);

        // Enhanced prompt for smarter categorization
        $prompt = "Analyze the following content and suggest exactly {$max_cats} relevant WordPress categories. 
        Rules:
        1. Return ONLY a comma-separated list of names. 
        2. Do NOT number them. 
        3. Do NOT use generic terms like 'Uncategorized', 'General', or 'Updates'. 
        4. Be specific to the topic.
        
        Content: " . $content_snippet;

        $categories = SynapseWP_API::generate($prompt);

        if (!is_wp_error($categories) && !empty($categories)) {
            $this->assign_categories($post_id, $categories);
        }

        // Re-hook in case it's needed later in the same execution (unlikely but good practice).
        add_action('publish_post', [$this, 'auto_categorize'], 10, 2);
    }

    /**
     * Assign categories to a post, creating them if they don't exist.
     *
     * @param int    $post_id     The post ID.
     * @param string $category_list Comma-separated list of category names.
     */
    private function assign_categories($post_id, $category_list)
    {
        $names = explode(',', $category_list);
        $ids = [];

        foreach ($names as $name) {
            $name = trim($name);
            // Cleanup: remove dot if it ends with one (common AI output)
            $name = rtrim($name, '.');

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

            // wp_set_post_categories with last arg false replaces all categories. 
            // We manipulated the array manually to include previous ones minus default, so we can use false (replace).
            wp_set_post_categories($post_id, $new_cats, false);
        }
    }
}
