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