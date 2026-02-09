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
                    <textarea id="synapsewp-ai-input" class="large-text" rows="4" placeholder="<?php esc_attr_e("Write an idea... (e.g. 'Benefits of React')", 'synapsewp'); ?>"></textarea>
            
                    <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between;">
                        <button type="button" id="synapsewp-ai-btn" class="button button-primary">
                            <?php esc_html_e('Expand with AI', 'synapsewp'); ?>
                        </button>
                        <span id="synapsewp-ai-spinner" class="spinner" style="float: none; margin: 0;"></span>
                    </div>
            
                    <div id="synapsewp-ai-result" style="margin-top:10px; padding:10px; background:#f0f0f1; border: 1px solid #ccd0d4; border-radius: 4px; display:none;"></div>
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

        // Hook our inline script. Using 'wp_add_inline_script' is cleaner than printing directly.
        // Since we don't have a registered handle for a custom file, we attach to 'jquery'.
        // A better practice is usually a separate file, but inline works for simple logic.
        $script = "
		jQuery(document).ready(function($) {
			$('#synapsewp-ai-btn').on('click', function(e) {
				e.preventDefault();
				
				var idea = $('#synapsewp-ai-input').val();
				if (!idea.trim()) {
					alert('" . esc_js(__('Please enter an idea to expand.', 'synapsewp')) . "');
					return;
				}

				var \$btn = $(this);
				var \$spinner = $('#synapsewp-ai-spinner');
				var \$resultBox = $('#synapsewp-ai-result');
				var originalText = \$btn.text();

				// UI Loading State
				\$btn.text('" . esc_js(__('Generating...', 'synapsewp')) . "').prop('disabled', true);
				\$spinner.addClass('is-active');
				\$resultBox.hide();

				$.ajax({
					url: '/wp-json/synapsewp/v1/expand',
					method: 'POST',
					contentType: 'application/json',
					beforeSend: function ( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', '" . wp_create_nonce('wp_rest') . "' );
					},
					data: JSON.stringify({ text: idea }),
					success: function( response ) {
						// Reset UI
						\$btn.text(originalText).prop('disabled', false);
						\$spinner.removeClass('is-active');

						if ( response.generated_text ) {
							var content = response.generated_text.replace(/\\n/g, '<br>');
							\$resultBox.html('<strong>" . esc_js(__('Result:', 'synapsewp')) . "</strong><br>' + content).fadeIn();
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
