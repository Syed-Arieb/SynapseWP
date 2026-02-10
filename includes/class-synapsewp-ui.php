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
            'normal',  // Changed from 'side' to 'normal' for better space
            'high'     // High priority to appear near top
        );
    }

    /**
     * Renders the content of the AI assistant meta box.
     */
    public function render_meta_box()
    {
?>
        <div class="synapsewp-ai-wrapper">
            <!-- Loading Overlay -->
            <div id="synapsewp-loading-overlay">
                <div class="synapsewp-pulse"></div>
            </div>

            <!-- Toast Container -->
            <div id="synapsewp-toast-container"></div>

            <!-- Tab Navigation -->
            <div class="synapsewp-tabs">
                <button type="button" class="synapsewp-tab-btn active" data-tab="chat">
                    <span class="dashicons dashicons-format-chat"></span>
                    <?php esc_html_e('AI Chat', 'synapsewp'); ?>
                </button>
                <button type="button" class="synapsewp-tab-btn" data-tab="tools">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e('Writing Tools', 'synapsewp'); ?>
                </button>
                <button type="button" class="synapsewp-tab-btn" data-tab="seo">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php esc_html_e('SEO Optimizer', 'synapsewp'); ?>
                </button>
                <button type="button" class="synapsewp-tab-btn" data-tab="templates">
                    <span class="dashicons dashicons-layout"></span>
                    <?php esc_html_e('Templates', 'synapsewp'); ?>
                </button>
            </div>

            <div class="synapsewp-tabs-content">
                <!-- Chat Tab -->
                <div class="synapsewp-tab-content active" data-tab-content="chat">
                    <div class="synapsewp-templates">
                        <span class="synapsewp-chip" data-prompt="Suggest 5 headline ideas for this topic">
                            <span class="dashicons dashicons-lightbulb"></span>
                            <?php esc_html_e('Headlines', 'synapsewp'); ?>
                        </span>
                        <span class="synapsewp-chip" data-prompt="Summarize the selected text">
                            <span class="dashicons dashicons-editor-justify"></span>
                            <?php esc_html_e('Summarize', 'synapsewp'); ?>
                        </span>
                        <span class="synapsewp-chip" data-prompt="Fix grammar and improve flow">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Fix Grammar', 'synapsewp'); ?>
                        </span>
                    </div>

                    <div class="synapsewp-chat-container">
                        <div class="synapsewp-chat-history">
                            <div class="synapsewp-message ai">
                                <?php esc_html_e('Hello! How can I help you write today? You can ask me to expand points, suggest titles, or improve your draft.', 'synapsewp'); ?>
                            </div>
                        </div>

                        <div class="synapsewp-chat-input-area">
                            <textarea id="synapsewp-chat-input" class="synapsewp-chat-input" rows="1"
                                placeholder="<?php esc_attr_e('Ask anything or give a writing prompt...', 'synapsewp'); ?>"></textarea>
                            <div class="synapsewp-chat-controls">
                                <div style="display: flex; gap: 10px;">
                                    <label>
                                        <input type="radio" name="synapsewp_mode" value="answer" checked>
                                        <?php esc_html_e('Chat', 'synapsewp'); ?>
                                    </label>
                                    <label>
                                        <input type="radio" name="synapsewp_mode" value="writer">
                                        <?php esc_html_e('Article Writer', 'synapsewp'); ?>
                                    </label>
                                </div>
                                <button type="button" id="synapsewp-send-btn" class="button button-primary synapsewp-action-btn">
                                    <span class="dashicons dashicons-paper-plane" style="margin-right: 5px;"></span>
                                    <?php esc_html_e('Send', 'synapsewp'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tools Tab -->
                <div class="synapsewp-tab-content" data-tab-content="tools">
                    <h4><span class="dashicons dashicons-edit"></span> <?php esc_html_e('Content Refinement', 'synapsewp'); ?></h4>
                    <p class="description"><?php esc_html_e('Advanced content manipulation tools based on selected text.', 'synapsewp'); ?></p>

                    <div class="synapsewp-tool-grid" style="margin-bottom: 25px;">
                        <div class="synapsewp-tool-group">
                            <label><?php esc_html_e('Summarization', 'synapsewp'); ?></label>
                            <div style="display: flex; gap: 5px;">
                                <select id="synapsewp-summary-length" class="synapsewp-param" style="flex: 1;">
                                    <option value="short"><?php esc_html_e('Concise (Short)', 'synapsewp'); ?></option>
                                    <option value="medium" selected><?php esc_html_e('Balanced (Medium)', 'synapsewp'); ?></option>
                                    <option value="long"><?php esc_html_e('Detailed (Long)', 'synapsewp'); ?></option>
                                </select>
                                <button type="button" class="button synapsewp-tool-btn" data-action="summarize">
                                    <span class="dashicons dashicons-media-text"></span>
                                </button>
                            </div>
                        </div>

                        <div class="synapsewp-tool-group">
                            <label><?php esc_html_e('Paraphrase / Rephrase', 'synapsewp'); ?></label>
                            <div style="display: flex; gap: 5px;">
                                <select id="synapsewp-tone" class="synapsewp-param" style="flex: 1;">
                                    <option value="professional" selected><?php esc_html_e('Professional', 'synapsewp'); ?></option>
                                    <option value="casual"><?php esc_html_e('Casual & Friendly', 'synapsewp'); ?></option>
                                    <option value="academic"><?php esc_html_e('Formal & Academic', 'synapsewp'); ?></option>
                                    <option value="creative"><?php esc_html_e('Creative & Bold', 'synapsewp'); ?></option>
                                </select>
                                <button type="button" class="button synapsewp-tool-btn" data-action="paraphrase">
                                    <span class="dashicons dashicons-update"></span>
                                </button>
                            </div>
                        </div>

                        <div class="synapsewp-tool-group">
                            <label><?php esc_html_e('Grammar & Flow', 'synapsewp'); ?></label>
                            <button type="button" class="button synapsewp-tool-btn" data-action="improve" style="width: 100%;">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php esc_html_e('Magic Improve', 'synapsewp'); ?>
                            </button>
                        </div>

                        <div class="synapsewp-tool-group">
                            <label><?php esc_html_e('Readability', 'synapsewp'); ?></label>
                            <button type="button" class="button synapsewp-tool-btn" data-action="simplify" style="width: 100%;">
                                <span class="dashicons dashicons-editor-alignleft"></span>
                                <?php esc_html_e('Simplify Text', 'synapsewp'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="synapsewp-tool-section">
                        <h4><span class="dashicons dashicons-translation"></span> <?php esc_html_e('Global Translation', 'synapsewp'); ?></h4>
                        <div class="synapsewp-tool-grid">
                            <div class="synapsewp-tool-group" style="grid-column: span 1;">
                                <label><?php esc_html_e('Target Language', 'synapsewp'); ?></label>
                                <select id="synapsewp-target-language" class="synapsewp-param">
                                    <option value="Spanish"><?php esc_html_e('Spanish', 'synapsewp'); ?></option>
                                    <option value="French"><?php esc_html_e('French', 'synapsewp'); ?></option>
                                    <option value="German"><?php esc_html_e('German', 'synapsewp'); ?></option>
                                    <option value="Portuguese"><?php esc_html_e('Portuguese', 'synapsewp'); ?></option>
                                    <option value="Italian"><?php esc_html_e('Italian', 'synapsewp'); ?></option>
                                    <option value="Japanese"><?php esc_html_e('Japanese', 'synapsewp'); ?></option>
                                    <option value="Chinese"><?php esc_html_e('Chinese', 'synapsewp'); ?></option>
                                    <option value="Arabic"><?php esc_html_e('Arabic', 'synapsewp'); ?></option>
                                    <option value="Hindi"><?php esc_html_e('Hindi', 'synapsewp'); ?></option>
                                    <option value="Russian"><?php esc_html_e('Russian', 'synapsewp'); ?></option>
                                </select>
                            </div>
                            <div class="synapsewp-tool-group" style="justify-content: flex-end;">
                                <button type="button" class="button button-primary synapsewp-tool-btn synapsewp-action-btn" data-action="translate">
                                    <span class="dashicons dashicons-translation"></span>
                                    <?php esc_html_e('Translate Selected', 'synapsewp'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEO Tab -->
                <div class="synapsewp-tab-content" data-tab-content="seo">
                    <h4><span class="dashicons dashicons-performance"></span> <?php esc_html_e('SEO Dashboard', 'synapsewp'); ?></h4>

                    <div style="margin-bottom: 20px;">
                        <button type="button" class="button button-primary synapsewp-action-btn" id="synapsewp-generate-meta" style="width: 100%;">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e('Generate AI Meta Tags', 'synapsewp'); ?>
                        </button>
                    </div>

                    <div id="synapsewp-seo-results" style="display: none;">
                        <div class="synapsewp-seo-card">
                            <header>
                                <span class="synapsewp-seo-label"><?php esc_html_e('Meta Description', 'synapsewp'); ?></span>
                                <button type="button" class="button button-small" id="synapsewp-copy-meta">
                                    <span class="dashicons dashicons-clipboard"></span>
                                    <?php esc_html_e('Copy', 'synapsewp'); ?>
                                </button>
                            </header>
                            <p id="synapsewp-meta-desc" class="synapsewp-seo-content"></p>
                        </div>

                        <div class="synapsewp-tool-grid">
                            <div class="synapsewp-seo-card">
                                <header><span class="synapsewp-seo-label"><?php esc_html_e('Keywords', 'synapsewp'); ?></span></header>
                                <div id="synapsewp-keywords" class="synapsewp-seo-content"></div>
                            </div>
                            <div class="synapsewp-seo-card">
                                <header><span class="synapsewp-seo-label"><?php esc_html_e('Optimization Score', 'synapsewp'); ?></span></header>
                                <div id="synapsewp-seo-score-container">
                                    <span id="synapsewp-seo-score" class="synapsewp-score-badge"></span>
                                </div>
                            </div>
                        </div>

                        <div class="synapsewp-seo-card">
                            <header><span class="synapsewp-seo-label"><?php esc_html_e('Actionable Suggestions', 'synapsewp'); ?></span></header>
                            <ul id="synapsewp-seo-suggestions" class="synapsewp-seo-content" style="padding-left: 20px; list-style-type: disc;"></ul>
                        </div>
                    </div>
                </div>

                <!-- Templates Tab -->
                <div class="synapsewp-tab-content" data-tab-content="templates">
                    <h4><span class="dashicons dashicons-layout"></span> <?php esc_html_e('Content Blueprints', 'synapsewp'); ?></h4>

                    <div class="synapsewp-tool-grid">
                        <button type="button" class="button button-secondary synapsewp-template-btn synapsewp-action-btn" data-action="generate-faq">
                            <span class="dashicons dashicons-editor-help"></span>
                            <?php esc_html_e('Instant FAQ Generator', 'synapsewp'); ?>
                        </button>

                        <button type="button" class="button button-secondary synapsewp-template-btn synapsewp-action-btn" data-action="bullet-summary">
                            <span class="dashicons dashicons-editor-ul"></span>
                            <?php esc_html_e('Bullet Summary', 'synapsewp'); ?>
                        </button>
                    </div>

                    <p class="description" style="margin-top: 15px;">
                        <?php esc_html_e('These templates use your entire post content to generate structured sections instantly.', 'synapsewp'); ?>
                    </p>
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
            'summarize_url' => '/wp-json/synapsewp/v1/summarize',
            'paraphrase_url' => '/wp-json/synapsewp/v1/paraphrase',
            'improve_url' => '/wp-json/synapsewp/v1/improve',
            'simplify_url' => '/wp-json/synapsewp/v1/simplify',
            'translate_url' => '/wp-json/synapsewp/v1/translate',
            'generate_meta_url' => '/wp-json/synapsewp/v1/generate-meta',
            'generate_faq_url' => '/wp-json/synapsewp/v1/generate-faq',
            'bullet_summary_url' => '/wp-json/synapsewp/v1/bullet-summary',
            'generate_alt_text_url' => '/wp-json/synapsewp/v1/generate-alt-text',
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }
}
