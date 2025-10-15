<?php

/**
 * Admin Menu class
 *
 * @package SureFeedback
 */

namespace SureFeedback\Admin;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin Menu class
 */
class Admin_Menu
{

    /**
     * Menu slug
     *
     * @var string
     */
    private $menu_slug = 'surefeedback';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Hook to admin_menu action to register menu when WordPress is ready
        add_action('admin_menu', array($this, 'register_menu'));
        // Enqueue our styles/scripts late so our CSS wins over WP admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'), 100);
        
        // Hide admin notices on our pages
        add_action('admin_head', array($this, 'hide_admin_notices'));
    }

    /**
     * Register admin menu
     */
    public function register_menu()
    {
        $plugin_name = get_option('surefeedback_plugin_name', false);
        $menu_title = $plugin_name ? esc_html($plugin_name) : __('SureFeedback', 'surefeedback');
        
        // Add main menu page
        add_menu_page(
            __('SureFeedback', 'surefeedback'), // Page title
            $menu_title, // Menu title
            'manage_options', // Capability
            $this->menu_slug, // Menu slug
            array($this, 'render_dashboard_page'), // Function
            '',// $this->get_menu_icon(), // Icon
            58 // Position (after Settings)
        );
        
        // Add dashboard submenu
        add_submenu_page(
            $this->menu_slug, // Parent slug
            __('Dashboard', 'surefeedback'), // Page title
            __('Dashboard', 'surefeedback'), // Menu title
            'manage_options', // Capability
            $this->menu_slug, // Menu slug
            array($this, 'render_dashboard_page') // Function
        );
        
        // Add settings submenu
        add_submenu_page(
            $this->menu_slug, // Parent slug
            __('Settings', 'surefeedback'), // Page title
            __('Settings', 'surefeedback'), // Menu title
            'manage_options', // Capability
            $this->menu_slug . '#settings', // Menu slug
            array($this, 'render_dashboard_page') // Function
        );

        // Add connection submenu
        add_submenu_page(
            $this->menu_slug, // Parent slug
            __('Connection', 'surefeedback'), // Page title
            __('Connection', 'surefeedback'), // Menu title
            'manage_options', // Capability
            $this->menu_slug . '#connection', // Menu slug
            array($this, 'render_dashboard_page') // Function
        );
        
        // Keep the old settings page for backward compatibility
        add_options_page(
            __('Feedback Connection', 'surefeedback'),
            $menu_title,
            'manage_options',
            'feedback-connection-options',
            array($this, 'render_legacy_page')
        );
    }

    /**
     * Get menu icon for SureFeedback
     *
     * @return string
     */
    private function get_menu_icon()
    {
        // Use the project huddle icon
        $icon_file = SUREFEEDBACK_PLUGIN_DIR . 'assets/project-huddle-icon.png';
        
        if (file_exists($icon_file)) {
            return SUREFEEDBACK_PLUGIN_URL . 'assets/project-huddle-icon.png';
        }
        
        // Fallback to dashicons if icon file doesn't exist
        return 'dashicons-format-chat';
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook)
    {
        // Only load on our plugin pages
        if (strpos($hook, $this->menu_slug) === false && $hook !== 'settings_page_feedback-connection-options') {
            return;
        }

        // Check if built React dashboard assets exist
        $js_file = SUREFEEDBACK_PLUGIN_DIR . 'assets/dist/admin.js';
        $css_file = SUREFEEDBACK_PLUGIN_DIR . 'assets/dist/admin.css';

        if (file_exists($js_file)) {
            wp_enqueue_script(
                'surefeedback-admin',
                SUREFEEDBACK_PLUGIN_URL . 'assets/dist/admin.js',
                array(),
                filemtime($js_file),
                true
            );
            
            // Add module type for ES6 imports
            add_filter('script_loader_tag', array($this, 'add_module_type_to_script'), 10, 3);

            // Localize script with WordPress data
            wp_localize_script(
                'surefeedback-admin',
                'sureFeedbackAdmin',
                $this->get_localized_data()
            );
        }

        if (file_exists($css_file)) {
            wp_enqueue_style(
                'surefeedback-admin',
                SUREFEEDBACK_PLUGIN_URL . 'assets/dist/admin.css',
                array(),
                filemtime($css_file)
            );
        }
        
        // Enqueue admin dashboard custom styles
        $dashboard_css = SUREFEEDBACK_PLUGIN_DIR . 'assets/admin-dashboard.css';
        if (file_exists($dashboard_css)) {
            wp_enqueue_style(
                'surefeedback-dashboard-custom',
                SUREFEEDBACK_PLUGIN_URL . 'assets/admin-dashboard.css',
                array(),
                filemtime($dashboard_css)
            );
        }
        
        // Enqueue WordPress admin styles
        wp_enqueue_style('common');
        wp_enqueue_style('forms');
        wp_enqueue_style('dashicons');
    }

    /**
     * Get localized data for admin scripts
     *
     * @return array
     */
    private function get_localized_data()
    {
        $surefeedback = \SureFeedback::get_instance();
        
        return array(
            'rest_url'         => rest_url(),
            'rest_nonce'       => wp_create_nonce('wp_rest'),
            'admin_url'        => admin_url(),
            'ajax_url'         => admin_url('admin-ajax.php'),
            'plugin_url'       => SUREFEEDBACK_PLUGIN_URL,
            'nonce'            => wp_create_nonce('surefeedback_admin_nonce'),
            'installer_nonce'  => wp_create_nonce('surefeedback_installer_nonce'),
            'disconnect_nonce' => wp_create_nonce('surefeedback-site-disconnect-nonce'),
            'showWhiteLabel'   => ! defined('PH_HIDE_WHITE_LABEL') || true !== PH_HIDE_WHITE_LABEL,
            'verification_status' => get_option('surefeedback_verification_status', 'unverified'),
            'connection_status' => get_option('surefeedback_connection_status', 'not_connected'),
            // Site token data for disconnect functionality
            'site_token'       => get_option('surefeedback_site_token', '') ?: get_option('surefeedback_api_key', ''),
            'api_token'        => get_option('surefeedback_api_key', ''),
            'site_domain'      => parse_url(home_url(), PHP_URL_HOST),
            'api_url'          => $surefeedback->get_api_url() . '/api/v1',
            'user_token'       => get_option('surefeedback_user_token', ''),
            // Connection data for auth.js
            'connection' => array(
                'app_url'          => $surefeedback->get_app_url(),
                'callback_url'     => admin_url('admin.php?page=surefeedback&action=callback'),
                'site_data' => array(
                    'domain'           => parse_url(home_url(), PHP_URL_HOST),
                    'site_name'        => get_bloginfo('name'),
                    'site_url'         => home_url(),
                    'admin_email'      => get_option('admin_email'),
                    'wp_version'       => get_bloginfo('version'),
                    'plugin_version'   => defined('SUREFEEDBACK_VERSION') ? SUREFEEDBACK_VERSION : '1.0.0',
                    'language'         => get_locale(),
                    'timezone'         => get_option('timezone_string') ?: 'UTC',
                    'theme'            => get_template(),
                    'active_plugins'   => count(get_option('active_plugins', [])),
                )
            ),
            // Asset URLs
            'icon_url'             => SUREFEEDBACK_PLUGIN_URL . 'assets/project-huddle-icon.png',
            'welcome_url'          => SUREFEEDBACK_PLUGIN_URL . 'assets/Video player.png',
            'settings_url'         => SUREFEEDBACK_PLUGIN_URL . 'assets/settings_unselected.svg',
            'settings_selected_url'=> SUREFEEDBACK_PLUGIN_URL . 'assets/settings.svg',
            'label_url'            => SUREFEEDBACK_PLUGIN_URL . 'assets/label.svg',
            'label_selected_url'   => SUREFEEDBACK_PLUGIN_URL . 'assets/label_selected.svg',
            'connection_url'       => SUREFEEDBACK_PLUGIN_URL . 'assets/connection.svg',
            'surerank_icon'        => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surerank.svg',
            'surecart_icon'        => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surecart.svg',
            'sureforms_icon'       => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/sureforms.svg',
            'presto_player_icon'   => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/pplayer.svg',
            'surefeedback_icon'    => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surefeedback.svg',
            'thumbs'               => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/thumbs.svg',
            'rocket'               => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/rocket.svg',
            'admin'                => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/admin.svg',
            'docs'                 => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/docs.svg',
            'welcome'              => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/welcome.png',
            'configure'            => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/configure_banner.png',
            'footer'               => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/footer.png',
            'welcome_background'   => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/welcome_background.png',
            'suretriggers_icon'    => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/OttoKit-Symbol-Primary.svg',
        );
    }

    /**
     * Add module type to script tag
     *
     * @param string $tag    The script tag.
     * @param string $handle The script handle.
     * @param string $src    The script source.
     * @return string
     */
    public function add_module_type_to_script($tag, $handle, $src)
    {
        if ('surefeedback-admin' === $handle) {
            $tag = str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }

    /**
     * Hide admin notices on the custom settings page
     *
     * @return void
     */
    public function hide_admin_notices()
    {
        $screen = get_current_screen();
        $pages_to_hide_notices = [
            'toplevel_page_surefeedback-dashboard',
            'settings_page_feedback-connection-options'
        ];
        
        if (in_array($screen->id, $pages_to_hide_notices)) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }

    /**
     * Render React app container
     */
    private function render_react_app()
    {
        echo '<div class="wrap">';
        echo '<div id="surefeedback-dashboard-app"></div>';
        echo '</div>';
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page()
    {
        $this->render_react_app();
    }

    /**
     * Render legacy options page
     */
    public function render_legacy_page()
    {
        echo '<div class="wrap">';
        echo '<div id="surefeedback-admin-app"></div>';
        echo '</div>';
    }
}