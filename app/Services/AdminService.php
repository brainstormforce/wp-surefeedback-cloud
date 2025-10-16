<?php

namespace SureFeedback\App\Services;

use SureFeedback\App\Repositories\SettingsRepository;
use SureFeedback\App\Repositories\ConnectionRepository;
use SureFeedback\App\Repositories\DashboardRepository;
use SureFeedback\App\Http\Requests\UpdateSettingsRequest;

/**
 * Admin Service
 *
 * Handles all WordPress admin functionality including menu creation,
 * settings pages, dashboard integration, and admin-specific features.
 *
 * @package SureFeedback\App\Services
 */
class AdminService
{
    /**
     * Settings Repository
     *
     * @var SettingsRepository
     */
    protected $settingsRepository;

    /**
     * Connection Repository
     *
     * @var ConnectionRepository
     */
    protected $connectionRepository;

    /**
     * Dashboard Repository
     *
     * @var DashboardRepository
     */
    protected $dashboardRepository;

    /**
     * Menu slug
     *
     * @var string
     */
    private $menu_slug = 'surefeedback';

    /**
     * Registered menu pages
     *
     * @var array
     */
    private $menu_pages = [];

    /**
     * Current page context
     *
     * @var string
     */
    private $current_page = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->settingsRepository = new SettingsRepository();
        $this->connectionRepository = new ConnectionRepository();
        $this->dashboardRepository = new DashboardRepository();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void
    {
        // Admin menu and pages
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'init_admin_settings']);
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_surefeedback_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_surefeedback_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_surefeedback_reset_plugin', [$this, 'ajax_reset_plugin']);
        add_action('wp_ajax_surefeedback_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_surefeedback_import_settings', [$this, 'ajax_import_settings']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'display_admin_notices']);
        
        // Plugin action links
        add_filter('plugin_action_links_' . SUREFEEDBACK_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);
        
        // Admin bar
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }

    /**
     * Register admin menu and subpages
     *
     * @return void
     */
    public function register_admin_menu(): void
    {
        // Main menu page
        $page_hook = add_menu_page(
            __('SureFeedback', 'surefeedback'),
            __('SureFeedback', 'surefeedback'),
            'manage_options',
            $this->menu_slug,
            [$this, 'render_main_page'],
            $this->get_menu_icon(),
            30
        );

        $this->menu_pages['main'] = $page_hook;

        // Dashboard submenu
        $dashboard_hook = add_submenu_page(
            $this->menu_slug,
            __('Dashboard', 'surefeedback'),
            __('Dashboard', 'surefeedback'),
            'manage_options',
            $this->menu_slug,
            [$this, 'render_main_page']
        );

        $this->menu_pages['dashboard'] = $dashboard_hook;

        // Settings submenu
        $settings_hook = add_submenu_page(
            $this->menu_slug,
            __('Settings', 'surefeedback'),
            __('Settings', 'surefeedback'),
            'manage_options',
            $this->menu_slug . '-settings',
            [$this, 'render_settings_page']
        );

        $this->menu_pages['settings'] = $settings_hook;

        // Connection submenu
        $connection_hook = add_submenu_page(
            $this->menu_slug,
            __('Connection', 'surefeedback'),
            __('Connection', 'surefeedback'),
            'manage_options',
            $this->menu_slug . '-connection',
            [$this, 'render_connection_page']
        );

        $this->menu_pages['connection'] = $connection_hook;

        // Tools submenu
        $tools_hook = add_submenu_page(
            $this->menu_slug,
            __('Tools', 'surefeedback'),
            __('Tools', 'surefeedback'),
            'manage_options',
            $this->menu_slug . '-tools',
            [$this, 'render_tools_page']
        );

        $this->menu_pages['tools'] = $tools_hook;

        // Add page-specific hooks
        foreach ($this->menu_pages as $page => $hook) {
            add_action("load-{$hook}", [$this, 'admin_page_load']);
        }
    }

    /**
     * Initialize admin settings
     *
     * @return void
     */
    public function init_admin_settings(): void
    {
        // Register settings sections and fields
        $this->register_general_settings();
        $this->register_connection_settings();
        $this->register_white_label_settings();
        $this->register_advanced_settings();
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void
    {
        // Only enqueue on our plugin pages
        if (!in_array($hook, $this->menu_pages)) {
            return;
        }

        // Enqueue admin styles
        wp_enqueue_style(
            'surefeedback-admin',
            SUREFEEDBACK_PLUGIN_URL . 'assets/admin-dashboard.css',
            [],
            SUREFEEDBACK_VERSION
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'surefeedback-admin',
            SUREFEEDBACK_PLUGIN_URL . 'dist/admin.js',
            ['wp-element', 'wp-api', 'wp-i18n'],
            SUREFEEDBACK_VERSION,
            true
        );

        // Localize script with admin data
        wp_localize_script('surefeedback-admin', 'surefeedbackAdmin', [
            'apiUrl' => rest_url('surefeedback/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxNonce' => wp_create_nonce('surefeedback_admin'),
            'currentUser' => wp_get_current_user(),
            'pluginUrl' => SUREFEEDBACK_PLUGIN_URL,
            'adminUrl' => admin_url('admin.php?page=' . $this->menu_slug),
            'settings' => $this->get_admin_settings(),
            'strings' => $this->get_localized_strings(),
            'capabilities' => $this->get_user_capabilities(),
            'environment' => $this->get_environment_info()
        ]);

        // Enqueue WordPress media uploader on settings page
        if (strpos($hook, 'settings') !== false) {
            wp_enqueue_media();
        }
    }

    /**
     * Render main admin page
     *
     * @return void
     */
    public function render_main_page(): void
    {
        $this->current_page = 'dashboard';
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<div id="surefeedback-admin-dashboard"></div>';
        echo '</div>';
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        $this->current_page = 'settings';
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<div id="surefeedback-admin-settings"></div>';
        echo '</div>';
    }

    /**
     * Render connection page
     *
     * @return void
     */
    public function render_connection_page(): void
    {
        $this->current_page = 'connection';
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<div id="surefeedback-admin-connection"></div>';
        echo '</div>';
    }

    /**
     * Render tools page
     *
     * @return void
     */
    public function render_tools_page(): void
    {
        $this->current_page = 'tools';
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<div id="surefeedback-admin-tools"></div>';
        echo '</div>';
    }

    /**
     * Admin page load handler
     *
     * @return void
     */
    public function admin_page_load(): void
    {
        // Add screen options, help tabs, etc.
        $this->add_screen_options();
        $this->add_help_tabs();
    }

    /**
     * Display admin notices
     *
     * @return void
     */
    public function display_admin_notices(): void
    {
        // Get connection data from repository
        $connectionData = $this->connectionRepository->getConnectionData();
        
        // Connection status notice
        if ($connectionData['status'] === 'disconnected' && $this->is_plugin_page()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>';
            echo esc_html__('SureFeedback is not connected to a parent site. ', 'surefeedback');
            echo '<a href="' . esc_url(admin_url('admin.php?page=' . $this->menu_slug . '-connection')) . '">';
            echo esc_html__('Connect now', 'surefeedback');
            echo '</a>';
            echo '</p>';
            echo '</div>';
        }

        // Verification status notice
        if ($connectionData['verification_status'] === 'failed' && $this->is_plugin_page()) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>';
            echo esc_html__('SureFeedback verification failed. Please check your connection settings.', 'surefeedback');
            echo '</p>';
            echo '</div>';
        }

        // Show success notices
        if (isset($_GET['message'])) {
           
            $message = sanitize_text_field($_GET['message']);
          
            $messages = [
                'settings_saved' => __('Settings saved successfully.', 'surefeedback'),
                'connection_successful' => __('Connection established successfully.', 'surefeedback'),
                'disconnected' => __('Disconnected successfully.', 'surefeedback'),
                'reset_complete' => __('Plugin reset completed.', 'surefeedback')
            ];

            if (isset($messages[$message])) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . esc_html($messages[$message]) . '</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing action links
     * @return array Modified action links
     */
    public function add_plugin_action_links(array $links): array
    {
        $plugin_links = [
            '<a href="' . esc_url(admin_url('admin.php?page=' . $this->menu_slug)) . '">' . 
            esc_html__('Dashboard', 'surefeedback') . '</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=' . $this->menu_slug . '-settings')) . '">' . 
            esc_html__('Settings', 'surefeedback') . '</a>'
        ];

        return array_merge($plugin_links, $links);
    }

    /**
     * Add admin bar menu
     *
     * @param \WP_Admin_Bar $wp_admin_bar WordPress admin bar object
     * @return void
     */
    public function add_admin_bar_menu(\WP_Admin_Bar $wp_admin_bar): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $connectionData = $this->connectionRepository->getConnectionData();
        $status_class = $connectionData['status'] === 'connected' ? 'connected' : 'disconnected';

        $wp_admin_bar->add_node([
            'id' => 'surefeedback',
            'title' => '<span class="ab-icon dashicons-feedback"></span><span class="ab-label">SureFeedback</span>',
            'href' => admin_url('admin.php?page=' . $this->menu_slug),
            'meta' => [
                'class' => 'surefeedback-admin-bar ' . $status_class
            ]
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'surefeedback',
            'id' => 'surefeedback-dashboard',
            'title' => __('Dashboard', 'surefeedback'),
            'href' => admin_url('admin.php?page=' . $this->menu_slug)
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'surefeedback',
            'id' => 'surefeedback-settings',
            'title' => __('Settings', 'surefeedback'),
            'href' => admin_url('admin.php?page=' . $this->menu_slug . '-settings')
        ]);

        if ($connectionData['status'] === 'connected') {
            $parent_url = $connectionData['parent_url'];
            if (!empty($parent_url)) {
                $wp_admin_bar->add_node([
                    'parent' => 'surefeedback',
                    'id' => 'surefeedback-parent',
                    'title' => __('Open Parent Dashboard', 'surefeedback'),
                    'href' => $parent_url,
                    'meta' => ['target' => '_blank']
                ]);
            }
        }
    }

    /**
     * Add dashboard widget
     *
     * @return void
     */
    public function add_dashboard_widget(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'surefeedback_dashboard_widget',
            __('SureFeedback Status', 'surefeedback'),
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Render dashboard widget
     *
     * @return void
     */
    public function render_dashboard_widget(): void
    {
        $connectionData = $this->connectionRepository->getConnectionData();
        $dashboardData = $this->dashboardRepository->getDashboardData();

        echo '<div class="surefeedback-dashboard-widget">';
        
        // Connection status
        echo '<p><strong>' . esc_html__('Connection Status:', 'surefeedback') . '</strong> ';
        if ($connectionData['status'] === 'connected') {
            echo '<span style="color: green;">' . esc_html__('Connected', 'surefeedback') . '</span>';
        } else {
            echo '<span style="color: red;">' . esc_html__('Disconnected', 'surefeedback') . '</span>';
        }
        echo '</p>';

        // Last verification
        if (!empty($connectionData['last_verification'])) {
            echo '<p><strong>' . esc_html__('Last Verification:', 'surefeedback') . '</strong> ';
            echo esc_html(human_time_diff(strtotime($connectionData['last_verification']), time()) . ' ago');
            echo '</p>';
        }

        // Pending feedback count
        echo '<p><strong>' . esc_html__('Pending Feedback:', 'surefeedback') . '</strong> ';
        echo esc_html($pending_feedback);
        echo '</p>';

        // Quick actions
        echo '<p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=' . $this->menu_slug)) . '" class="button">';
        echo esc_html__('View Dashboard', 'surefeedback');
        echo '</a> ';
        
        if ($connection_status !== 'connected') {
            echo '<a href="' . esc_url(admin_url('admin.php?page=' . $this->menu_slug . '-connection')) . '" class="button-primary">';
            echo esc_html__('Connect Now', 'surefeedback');
            echo '</a>';
        }
        echo '</p>';
        
        echo '</div>';
    }

    /**
     * Register general settings
     *
     * @return void
     */
    private function register_general_settings(): void
    {
        register_setting('surefeedback_general', 'surefeedback_widget_enabled');
        register_setting('surefeedback_general', 'surefeedback_widget_position');
        register_setting('surefeedback_general', 'surefeedback_widget_theme');
        register_setting('surefeedback_general', 'surefeedback_allowed_roles');
        register_setting('surefeedback_general', 'surefeedback_guest_comments');
        register_setting('surefeedback_general', 'surefeedback_email_notifications');
    }

    /**
     * Register connection settings
     *
     * @return void
     */
    private function register_connection_settings(): void
    {
        register_setting('surefeedback_connection', 'surefeedback_parent_url');
        register_setting('surefeedback_connection', 'surefeedback_access_token');
        register_setting('surefeedback_connection', 'surefeedback_site_token');
    }

    /**
     * Register white label settings
     *
     * @return void
     */
    private function register_white_label_settings(): void
    {
        register_setting('surefeedback_white_label', 'surefeedback_plugin_name');
        register_setting('surefeedback_white_label', 'surefeedback_plugin_description');
        register_setting('surefeedback_white_label', 'surefeedback_plugin_author');
        register_setting('surefeedback_white_label', 'surefeedback_plugin_author_url');
        register_setting('surefeedback_white_label', 'surefeedback_company_logo');
        register_setting('surefeedback_white_label', 'surefeedback_hide_branding');
    }

    /**
     * Register advanced settings
     *
     * @return void
     */
    private function register_advanced_settings(): void
    {
        register_setting('surefeedback_advanced', 'surefeedback_debug_mode');
        register_setting('surefeedback_advanced', 'surefeedback_cache_duration');
        register_setting('surefeedback_advanced', 'surefeedback_api_timeout');
        register_setting('surefeedback_advanced', 'surefeedback_max_file_size');
        register_setting('surefeedback_advanced', 'surefeedback_allowed_file_types');
    }

    /**
     * Get menu icon
     *
     * @return string
     */
    private function get_menu_icon(): string
    {
        return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEwIDJDNS41ODEyIDIgMiA1LjU4MTIgMiAxMEMyIDEyLjc2MjYgMy40NDU3NSAxNS4xOTQ2IDUuNjI1IDE2LjU2MjVMNSAxOEg3TDcuNSAxNkg5VjE0SDExVjE2SDEyLjVMMTMgMThIMTVMMTQuMzc1IDE2LjU2MjVDMTYuNTU0MiAxNS4xOTQ2IDE4IDEyLjc2MjYgMTggMTBDMTggNS41ODEyIDE0LjQxODggMiAxMCAyWk03IDhDNy41NTIyOCA4IDggNy41NTIyOCA4IDdDOCA2LjQ0NzcyIDcuNTUyMjggNiA3IDZDNi40NDc3MiA2IDYgNi40NDc3MiA2IDdDNiA3LjU1MjI4IDYuNDQ3NzIgOCA3IDhaTTEzIDhDMTMuNTUyMyA4IDE0IDcuNTUyMjggMTQgN0MxNCA2LjQ0NzcyIDEzLjU1MjMgNiAxMyA2QzEyLjQ0NzcgNiAxMiA2LjQ0NzcyIDEyIDdDMTIgNy41NTIyOCAxMi40NDc3IDggMTMgOFpNMTAgMTJDMTEuMTA0NiAxMiAxMiAxMS4xMDQ2IDEyIDEwSDhDOCAxMS4xMDQ2IDguODk1NDMgMTIgMTAgMTJaIiBmaWxsPSIjOWNhM2FmIi8+Cjwvc3ZnPgo=';
    }

    /**
     * Get admin settings for JavaScript
     *
     * @return array
     */
    private function get_admin_settings(): array
    {
        $connectionData = $this->connectionRepository->getConnectionData();
        $settingsData = $this->settingsRepository->getSettings();
        
        return [
            'connected' => $connectionData['status'] === 'connected',
            'parentUrl' => $connectionData['parent_url'],
            'siteId' => $connectionData['site_id'],
            'verificationStatus' => $connectionData['verification_status'],
            'lastVerification' => $connectionData['last_verification'],
            'widgetEnabled' => $settingsData['widget_enabled'],
            'debugMode' => $settingsData['debug_mode']
        ];
    }

    /**
     * Get localized strings for JavaScript
     *
     * @return array
     */
    private function get_localized_strings(): array
    {
        return [
            'connecting' => __('Connecting...', 'surefeedback'),
            'connected' => __('Connected', 'surefeedback'),
            'disconnected' => __('Disconnected', 'surefeedback'),
            'testing' => __('Testing connection...', 'surefeedback'),
            'saving' => __('Saving...', 'surefeedback'),
            'saved' => __('Saved', 'surefeedback'),
            'error' => __('Error', 'surefeedback'),
            'success' => __('Success', 'surefeedback'),
            'confirmDisconnect' => __('Are you sure you want to disconnect? This will disable the feedback widget.', 'surefeedback'),
            'confirmReset' => __('Are you sure you want to reset all settings? This action cannot be undone.', 'surefeedback')
        ];
    }

    /**
     * Get user capabilities for JavaScript
     *
     * @return array
     */
    private function get_user_capabilities(): array
    {
        return [
            'manage_options' => current_user_can('manage_options'),
            'edit_posts' => current_user_can('edit_posts'),
            'upload_files' => current_user_can('upload_files')
        ];
    }

    /**
     * Get environment info for JavaScript
     *
     * @return array
     */
    private function get_environment_info(): array
    {
        return [
            'pluginVersion' => SUREFEEDBACK_VERSION,
            'wordpressVersion' => get_bloginfo('version'),
            'phpVersion' => PHP_VERSION,
            'environment' => defined('SUREFEEDBACK_MODE') ? SUREFEEDBACK_MODE : 'development'
        ];
    }

    /**
     * Check if current page is a plugin page
     *
     * @return bool
     */
    private function is_plugin_page(): bool
    {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, $this->menu_slug) !== false;
    }

    /**
     * Add screen options
     *
     * @return void
     */
    private function add_screen_options(): void
    {
        // Add screen options for plugin pages
        add_screen_option('layout_columns', ['max' => 2, 'default' => 2]);
    }

    /**
     * Add help tabs
     *
     * @return void
     */
    private function add_help_tabs(): void
    {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, $this->menu_slug) === false) {
            return;
        }

        $screen->add_help_tab([
            'id' => 'surefeedback_overview',
            'title' => __('Overview', 'surefeedback'),
            'content' => $this->get_help_overview()
        ]);

        $screen->add_help_tab([
            'id' => 'surefeedback_connection',
            'title' => __('Connection', 'surefeedback'),
            'content' => $this->get_help_connection()
        ]);

        $screen->add_help_tab([
            'id' => 'surefeedback_troubleshooting',
            'title' => __('Troubleshooting', 'surefeedback'),
            'content' => $this->get_help_troubleshooting()
        ]);

        $screen->set_help_sidebar($this->get_help_sidebar());
    }

    /**
     * Get help overview content
     *
     * @return string
     */
    private function get_help_overview(): string
    {
        return '<p>' . esc_html__('SureFeedback allows clients to provide feedback directly on your website using a sticky note-style interface. The feedback is then synced with your parent SureFeedback dashboard for centralized management.', 'surefeedback') . '</p>';
    }

    /**
     * Get help connection content
     *
     * @return string
     */
    private function get_help_connection(): string
    {
        return '<p>' . esc_html__('To connect this site to your SureFeedback parent dashboard, you need the parent site URL and a site token. These are provided when you add a new site in your parent dashboard.', 'surefeedback') . '</p>';
    }

    /**
     * Get help troubleshooting content
     *
     * @return string
     */
    private function get_help_troubleshooting(): string
    {
        return '<p>' . esc_html__('If you are experiencing connection issues, please check that your parent site URL is correct and that the site token matches what was provided in your parent dashboard.', 'surefeedback') . '</p>';
    }

    /**
     * Get help sidebar content
     *
     * @return string
     */
    private function get_help_sidebar(): string
    {
        return '<p><strong>' . esc_html__('For more information:', 'surefeedback') . '</strong></p>' .
               '<p><a href="https://surefeedback.com/docs" target="_blank">' . esc_html__('Documentation', 'surefeedback') . '</a></p>' .
               '<p><a href="https://surefeedback.com/support" target="_blank">' . esc_html__('Support', 'surefeedback') . '</a></p>';
    }

    /**
     * AJAX handler for saving settings
     *
     * @return void
     */
    public function ajax_save_settings(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'surefeedback'));
        }

        try {
            // Validate request data
            $request = new UpdateSettingsRequest($_POST);
            $validatedData = $request->validated();

            // Save settings through repository
            $this->settingsRepository->updateSettings($validatedData);

            wp_send_json_success([
                'message' => __('Settings saved successfully', 'surefeedback'),
                'settings' => $validatedData
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to save settings', 'surefeedback'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for testing connection
     *
     * @return void
     */
    public function ajax_test_connection(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'surefeedback'));
        }

        // Get SaaS client service and test connection
        try {
            $saas_client = new SaasClientService();
            $result = $saas_client->verify_script_integration();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for resetting plugin
     *
     * @return void
     */
    public function ajax_reset_plugin(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'surefeedback'));
        }

        // Reset all plugin options
        $options_to_delete = [
            'surefeedback_parent_url',
            'surefeedback_site_token',
            'surefeedback_id',
            'surefeedback_access_token',
            'surefeedback_script_url',
            'surefeedback_connection_status',
            'surefeedback_connection_date',
            'surefeedback_last_verification',
            'surefeedback_verification_status',
            'surefeedback_retry_count',
            'surefeedback_last_heartbeat',
            'surefeedback_widget_enabled',
            'surefeedback_widget_position',
            'surefeedback_widget_theme',
            'surefeedback_allowed_roles',
            'surefeedback_guest_comments',
            'surefeedback_email_notifications',
            'surefeedback_debug_mode',
            'surefeedback_cache_duration',
            'surefeedback_api_timeout',
            'surefeedback_max_file_size',
            'surefeedback_allowed_file_types'
        ];

        foreach ($options_to_delete as $option) {
            delete_option($option);
        }

        // Clear scheduled events
        wp_clear_scheduled_hook('surefeedback_auto_verify');
        wp_clear_scheduled_hook('surefeedback_hourly_verify');

        wp_send_json_success([
            'message' => __('Plugin reset successfully', 'surefeedback')
        ]);
    }

    /**
     * AJAX handler for exporting settings
     *
     * @return void
     */
    public function ajax_export_settings(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'surefeedback'));
        }

        // Get all settings through repositories
        $settings = $this->settingsRepository->getSettings();
        $connectionData = $this->connectionRepository->getConnectionData();

        // Combine settings for export
        $exportData = array_merge($settings, [
            'connection_status' => $connectionData['status'],
            'parent_url' => $connectionData['parent_url'],
            'site_id' => $connectionData['site_id']
        ]);

        wp_send_json_success([
            'settings' => $exportData,
            'exported_at' => current_time('mysql')
        ]);
    }

    /**
     * AJAX handler for importing settings
     *
     * @return void
     */
    public function ajax_import_settings(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'surefeedback'));
        }

        try {
            $settings = $_POST['settings'] ?? [];
            
            if (empty($settings)) {
                wp_send_json_error(__('No settings provided', 'surefeedback'));
            }

            // Separate settings and connection data
            $settingsData = [];
            $connectionData = [];

            foreach ($settings as $key => $value) {
                if (in_array($key, ['connection_status', 'parent_url', 'site_id'])) {
                    $connectionData[$key] = $value;
                } else {
                    $settingsData[$key] = $value;
                }
            }

            // Import through repositories
            if (!empty($settingsData)) {
                $this->settingsRepository->updateSettings($settingsData);
            }
            
            if (!empty($connectionData)) {
                $this->connectionRepository->updateConnection($connectionData);
            }

            wp_send_json_success([
                'message' => __('Settings imported successfully', 'surefeedback'),
                'imported' => array_merge($settingsData, $connectionData)
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to import settings', 'surefeedback'),
                'error' => $e->getMessage()
            ]);
        }
    }
}