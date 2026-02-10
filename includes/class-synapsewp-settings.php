<?php

if (!defined('ABSPATH')) {
    exit;
}

class SynapseWP_Settings
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_plugin_page()
    {
        add_menu_page(
            'SynapseWP',
            'SynapseWP',
            'manage_options',
            'synapsewp-settings',
            array($this, 'create_admin_page'),
            'dashicons-superhero',
            6
        );
    }

    public function register_settings()
    {
        register_setting('synapsewp_option_group', 'sma_ai_key', 'sanitize_text_field');
        register_setting('synapsewp_option_group', 'sma_ai_model', 'sanitize_text_field');
        register_setting('synapsewp_option_group', 'sma_max_categories', 'intval');
        register_setting('synapsewp_option_group', 'sma_default_language', 'sanitize_text_field');
        register_setting('synapsewp_option_group', 'sma_auto_alt_text', 'rest_sanitize_boolean');

        add_settings_section(
            'synapsewp_main_section',
            'AI Configuration',
            null,
            'synapsewp-settings'
        );

        add_settings_field(
            'sma_ai_key',
            'Gemini API Key',
            array($this, 'render_ai_key_field'),
            'synapsewp-settings',
            'synapsewp_main_section'
        );

        add_settings_field(
            'sma_ai_model',
            'AI Model',
            array($this, 'render_ai_model_field'),
            'synapsewp-settings',
            'synapsewp_main_section'
        );

        add_settings_field(
            'sma_max_categories',
            'Max Categories',
            array($this, 'render_max_categories_field'),
            'synapsewp-settings',
            'synapsewp_main_section'
        );

        add_settings_field(
            'sma_default_language',
            'Default Language',
            array($this, 'render_default_language_field'),
            'synapsewp-settings',
            'synapsewp_main_section'
        );

        add_settings_field(
            'sma_auto_alt_text',
            'Auto Alt-Text Generation',
            array($this, 'render_auto_alt_text_field'),
            'synapsewp-settings',
            'synapsewp_main_section'
        );
    }

    public function render_ai_key_field()
    {
        $value = get_option('sma_ai_key');
?>
        <input type="password" name="sma_ai_key" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">Enter your Google Gemini API Key. <a href="https://makersuite.google.com/app/apikey"
                target="_blank">Get one here</a>.</p>
    <?php
    }

    public function render_ai_model_field()
    {
        $model = get_option('sma_ai_model', 'gemini-2.5-flash-lite');
    ?>
        <select name="sma_ai_model">
            <option value="gemini-2.5-flash-lite" <?php selected($model, 'gemini-2.5-flash-lite'); ?>>Gemini 2.5 Flash Lite
                (Fastest)</option>
            <option value="gemini-2.5-flash" <?php selected($model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash (Balanced)
            </option>
            <option value="gemini-2.5-pro" <?php selected($model, 'gemini-2.5-pro'); ?>>Gemini 2.5 Pro (Best Quality)</option>
        </select>
        <p class="description">Select the model to use for generation.</p>
    <?php
    }

    public function render_max_categories_field()
    {
        $value = get_option('sma_max_categories', 3);
    ?>
        <input type="number" name="sma_max_categories" value="<?php echo esc_attr($value); ?>" min="1" max="10" step="1"
            class="small-text">
        <p class="description">Maximum number of AI-generated categories per post.</p>
    <?php
    }

    public function render_default_language_field()
    {
        $language = get_option('sma_default_language', 'English');
    ?>
        <select name="sma_default_language">
            <option value="English" <?php selected($language, 'English'); ?>>English</option>
            <option value="Spanish" <?php selected($language, 'Spanish'); ?>>Spanish</option>
            <option value="French" <?php selected($language, 'French'); ?>>French</option>
            <option value="German" <?php selected($language, 'German'); ?>>German</option>
            <option value="Portuguese" <?php selected($language, 'Portuguese'); ?>>Portuguese</option>
            <option value="Italian" <?php selected($language, 'Italian'); ?>>Italian</option>
            <option value="Japanese" <?php selected($language, 'Japanese'); ?>>Japanese</option>
            <option value="Chinese" <?php selected($language, 'Chinese'); ?>>Chinese (Simplified)</option>
            <option value="Arabic" <?php selected($language, 'Arabic'); ?>>Arabic</option>
            <option value="Hindi" <?php selected($language, 'Hindi'); ?>>Hindi</option>
            <option value="Russian" <?php selected($language, 'Russian'); ?>>Russian</option>
            <option value="Korean" <?php selected($language, 'Korean'); ?>>Korean</option>
        </select>
        <p class="description">Default language for content generation and translation.</p>
    <?php
    }

    public function render_auto_alt_text_field()
    {
        $enabled = get_option('sma_auto_alt_text', false);
    ?>
        <label>
            <input type="checkbox" name="sma_auto_alt_text" value="1" <?php checked($enabled, true); ?>>
            <?php esc_html_e('Automatically generate alt text when uploading images', 'synapsewp'); ?>
        </label>
        <p class="description">When enabled, AI will automatically generate descriptive alt text for newly uploaded images.</p>
    <?php
    }

    public function create_admin_page()
    {
    ?>
        <div class="wrap">
            <h1>SynapseWP AI Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('synapsewp_option_group');
                do_settings_sections('synapsewp-settings');
                submit_button();
                ?>
            </form>
        </div>
<?php
    }
}
