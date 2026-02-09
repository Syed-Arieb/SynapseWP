<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SynapseWP_UI
 *
 * Handles the UI integration and meta boxes for the AI Writer assistant in the WordPress admin.
 */
class SynapseWP_UI
{

    /**
     * SynapseWP_UI constructor.
     *
     * Initializes the hooks for meta boxes and admin scripts.
     */
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_writer_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_ai_scripts']);
    }

    /**
     * Registers the "AI Writing Assistant" meta box.
     */
    public function add_writer_meta_box()
    {
        add_meta_box(
            'synapsewp_writer_box',
            __('SynapseWP AI Assistant', 'synapsewp'),
            [$this, 'render_meta_box'],
            'post',
            'side',
            'high'
        );
    }

    /**
     * Renders the content of the AI assistant meta box.
     */
    public function render_meta_box()
    {
        ?>
        <div class="synapsewp-ai-wrapper">
            <!-- Mode Toggle -->
            <div style="margin-bottom: 10px; display: flex; gap: 15px; font-size: 12px; color: #666;">
                <label>
                    <input type="radio" name="synapsewp_mode" value="answer" checked>
                    <?php esc_html_e('Chat Mode', 'synapsewp'); ?>
                </label>
                <label>
                    <input type="radio" name="synapsewp_mode" value="writer">
                    <?php esc_html_e('Writer Mode', 'synapsewp'); ?>
                </label>
            </div>

            <!-- Templates -->
            <div class="synapsewp-templates">
                <span class="synapsewp-chip"
                    data-prompt="Suggest 5 headline ideas for this topic"><?php esc_html_e('Headlines', 'synapsewp'); ?></span>
                <span class="synapsewp-chip"
                    data-prompt="Summarize the selected text"><?php esc_html_e('Summarize', 'synapsewp'); ?></span>
                <span class="synapsewp-chip"
                    data-prompt="Fix grammar and improve flow"><?php esc_html_e('Fix Grammar', 'synapsewp'); ?></span>
                <span class="synapsewp-chip"
                    data-prompt="Write an intro paragraph regarding..."><?php esc_html_e('Intro', 'synapsewp'); ?></span>
            </div>

            <!-- Chat Container -->
            <div class="synapsewp-chat-container">
                <div class="synapsewp-chat-history">
                    <div class="synapsewp-message ai">
                        <?php esc_html_e('Hello! How can I help you write today?', 'synapsewp'); ?>
                    </div>
                </div>

                <div class="synapsewp-chat-input-area">
                    <textarea id="synapsewp-chat-input" class="synapsewp-chat-input" rows="1"
                        placeholder="<?php esc_attr_e('Type a message...', 'synapsewp'); ?>"></textarea>
                    <div class="synapsewp-chat-controls">
                        <small style="color: #888;">Enter to send, Shift+Enter for new line</small>
                        <button type="button" id="synapsewp-send-btn" class="button button-primary">
                            <?php esc_html_e('Send', 'synapsewp'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueues the necessary assets for the AI functionality.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_ai_scripts($hook)
    {
        global $post;

        if (!in_array($hook, ['post.php', 'post-new.php']) || !$post || 'post' !== $post->post_type) {
            return;
        }

        // Enqueue Assets
        wp_enqueue_style('synapsewp-admin-css', SYNAPSEWP_PLUGIN_URL . 'assets/css/synapsewp-admin.css', [], SYNAPSEWP_VERSION);
        wp_enqueue_script('synapsewp-admin-js', SYNAPSEWP_PLUGIN_URL . 'assets/js/synapsewp-admin.js', ['jquery', 'wp-blocks', 'wp-element', 'wp-components', 'wp-data'], SYNAPSEWP_VERSION, true);

        // Localize Script for JS variables
        wp_localize_script('synapsewp-admin-js', 'synapsewp_vars', [
            'ajax_url' => '/wp-json/synapsewp/v1/expand',
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }
}
