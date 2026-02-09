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
        <div class="synapsewp-ai-container">
            <textarea id="synapsewp-ai-input" class="large-text" rows="4"
                placeholder="<?php esc_attr_e("Write an idea or topic...", 'synapsewp'); ?>"></textarea>

            <div style="margin-top: 10px; margin-bottom: 10px;">
                <label style="margin-right: 15px;">
                    <input type="radio" name="synapsewp_mode" value="answer" checked>
                    <?php esc_html_e('Answer Mode', 'synapsewp'); ?>
                </label>
                <label>
                    <input type="radio" name="synapsewp_mode" value="writer">
                    <?php esc_html_e('Writer Mode', 'synapsewp'); ?>
                </label>
                <p class="description" style="margin-top: 5px; font-size: 12px;">
                    <em><?php esc_html_e('Answer: Shows result below. Writer: Inserts into post & updates title.', 'synapsewp'); ?></em>
                </p>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between;">
                <button type="button" id="synapsewp-ai-btn" class="button button-primary">
                    <?php esc_html_e('Generate', 'synapsewp'); ?>
                </button>
                <span id="synapsewp-ai-spinner" class="spinner" style="float: none; margin: 0;"></span>
            </div>

            <div id="synapsewp-ai-result"
                style="margin-top:10px; padding:10px; background:#f0f0f1; border: 1px solid #ccd0d4; border-radius: 4px; display:none;">
            </div>
        </div>
        <?php
    }

    /**
     * Enceues the necessary JavaScript for the AI functionality.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_ai_scripts($hook)
    {
        global $post;

        // Only load on post edit screens and if we have a valid post object.
        if (!in_array($hook, ['post.php', 'post-new.php']) || !$post || 'post' !== $post->post_type) {
            return;
        }

        // Enqueue jQuery (standard WP dependency).
        wp_enqueue_script('jquery');

        $script = "
        jQuery(document).ready(function($) {
            $('#synapsewp-ai-btn').on('click', function(e) {
                e.preventDefault();
                
                var idea = $('#synapsewp-ai-input').val();
                var mode = $('input[name=\"synapsewp_mode\"]:checked').val();

                if (!idea.trim()) {
                    alert('" . esc_js(__('Please enter an idea.', 'synapsewp')) . "');
                    return;
                }

                var \$btn = $(this);
                var \$spinner = $('#synapsewp-ai-spinner');
                var \$resultBox = $('#synapsewp-ai-result');
                var originalText = \$btn.text();

                // UI Loading State
                \$btn.text('" . esc_js(__('Generating...', 'synapsewp')) . "').prop('disabled', true);
                \$spinner.addClass('is-active');
                if (mode === 'answer') {
                    \$resultBox.hide();
                }

                $.ajax({
                    url: '/wp-json/synapsewp/v1/expand',
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: function ( xhr ) {
                        xhr.setRequestHeader( 'X-WP-Nonce', '" . wp_create_nonce('wp_rest') . "' );
                    },
                    data: JSON.stringify({ text: idea, mode: mode }),
                    success: function( response ) {
                        // Reset UI
                        \$btn.text(originalText).prop('disabled', false);
                        \$spinner.removeClass('is-active');

                        if ( response.data ) {
                            if (mode === 'writer') {
                                // Writer Mode: Insert directly into editor
                                var title = response.data.title;
                                var content = response.data.content;

                                // Update Title
                                if (title) {
                                    // Gutenberg
                                    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                                        wp.data.dispatch('core/editor').editPost({ title: title });
                                    } else {
                                        // Classic
                                        $('#title').val(title);
                                        $('#title-prompt-text').hide();
                                    }
                                }

                                // Update Content
                                if (content) {
                                    // Gutenberg
                                    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                                        // Create a block from the content
                                        var block = wp.blocks.createBlock('core/paragraph', { content: content });
                                        wp.data.dispatch('core/editor').insertBlocks(block);
                                    } else if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                                        // Classic TinyMCE
                                        tinymce.activeEditor.insertContent(content);
                                    } else {
                                        // Classic Textarea fallback
                                        var currentContent = $('#content').val();
                                        $('#content').val(currentContent + '\\n\\n' + content);
                                    }
                                }
                                
                                alert('" . esc_js(__('Content inserted successfully!', 'synapsewp')) . "');

                            } else {
                                // Answer Mode: Show in result box
                                var displayContent = response.data.content || response.data.generated_text; // Fallback for backward compatibility
                                if (displayContent) {
                                     var formatted = displayContent.replace(/\\n/g, '<br>');
                                    \$resultBox.html('<strong>" . esc_js(__('Result:', 'synapsewp')) . "</strong><br>' + formatted).fadeIn();
                                }
                            }
                        } else {
                            alert('" . esc_js(__('AI returned an empty response.', 'synapsewp')) . "');
                        }
                    },
                    error: function( xhr, status, error ) {
                        // Reset UI
                        \$btn.text(originalText).prop('disabled', false);
                        \$spinner.removeClass('is-active');

                        var errorMsg = '" . esc_js(__('Error communicating with AI.', 'synapsewp')) . "';
                        if ( xhr.responseJSON && xhr.responseJSON.message ) {
                            errorMsg += '\\n' + xhr.responseJSON.message;
                        }
                        alert(errorMsg);
                    }
                });
            });
        });
        ";

        wp_add_inline_script('jquery', $script);
    }
}
