<?php

namespace SureFeedback\App\Services;

/**
 * Frontend Service
 *
 * Handles frontend script injection, widget loading, and client-side
 * functionality for the SureFeedback feedback collection system.
 *
 * @package SureFeedback\App\Services
 */
class FrontendService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void
    {
        // Inject widget script on frontend for connected sites
        add_action('wp_footer', [$this, 'inject_widget_script']);
        
        // Add frontend styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Handle widget configuration endpoint
        add_action('wp_ajax_nopriv_surefeedback_widget_config', [$this, 'ajax_widget_config']);
        add_action('wp_ajax_surefeedback_widget_config', [$this, 'ajax_widget_config']);
    }

    /**
     * Inject SureFeedback widget script on frontend for connected sites
     *
     * @return void
     */
    public function inject_widget_script(): void
    {
        // Only inject on frontend, not in admin
        if (is_admin()) {
            return;
        }

        // Check if site is connected and has proper tokens
        $site_id = get_option('surefeedback_id');
        $access_token = get_option('surefeedback_access_token');
        $connection_status = get_option('surefeedback_connection_status', 'disconnected');

        if (empty($site_id) || empty($access_token) || $connection_status !== 'connected') {
            return;
        }

        // Check if feedback widget is enabled
        $widget_enabled = get_option('surefeedback_widget_enabled', true);
        if (!$widget_enabled) {
            return;
        }

        // Skip injection on certain pages or conditions
        if ($this->should_skip_injection()) {
            return;
        }

        $this->render_widget_script();
    }

    /**
     * Enqueue frontend assets
     *
     * @return void
     */
    public function enqueue_frontend_assets(): void
    {
        // Only enqueue on frontend
        if (is_admin()) {
            return;
        }

        $connection_status = get_option('surefeedback_connection_status', 'disconnected');
        $widget_enabled = get_option('surefeedback_widget_enabled', true);

        if ($connection_status !== 'connected' || !$widget_enabled) {
            return;
        }

        // Enqueue widget styles
        wp_enqueue_style(
            'surefeedback-widget',
            SUREFEEDBACK_PLUGIN_URL . 'assets/widget.css',
            [],
            SUREFEEDBACK_VERSION
        );

        // Enqueue widget script
        wp_enqueue_script(
            'surefeedback-widget',
            SUREFEEDBACK_PLUGIN_URL . 'assets/widget.js',
            ['jquery'],
            SUREFEEDBACK_VERSION,
            true
        );

        // Localize script with configuration
        wp_localize_script('surefeedback-widget', 'surefeedbackConfig', [
            'apiUrl' => rest_url('surefeedback/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'siteId' => get_option('surefeedback_id'),
            'accessToken' => get_option('surefeedback_access_token'),
            'settings' => $this->get_widget_settings(),
            'user' => $this->get_current_user_data(),
            'page' => $this->get_current_page_data()
        ]);
    }

    /**
     * Render widget script directly in footer
     *
     * @return void
     */
    private function render_widget_script(): void
    {
        $site_id = get_option('surefeedback_id');
        $access_token = get_option('surefeedback_access_token');
        $parent_url = get_option('surefeedback_parent_url');
        $script_url = get_option('surefeedback_script_url');

        // Use script URL if available, otherwise construct from parent URL
        if (empty($script_url) && !empty($parent_url)) {
            $script_url = trailingslashit($parent_url) . 'assets/widget.js';
        }

        if (empty($script_url)) {
            error_log('SureFeedback: No script URL available for widget injection');
            return;
        }

        $widget_config = [
            'siteId' => $site_id,
            'accessToken' => $access_token,
            'apiUrl' => rest_url('surefeedback/v1/'),
            'parentUrl' => $parent_url,
            'currentUrl' => get_permalink(),
            'pageTitle' => get_the_title(),
            'pageId' => get_the_ID(),
            'settings' => $this->get_widget_settings(),
            'user' => $this->get_current_user_data(),
            'nonce' => wp_create_nonce('wp_rest')
        ];

        echo "\n<!-- SureFeedback Widget -->\n";
        echo '<script type="text/javascript">';
        echo 'window.SureFeedbackConfig = ' . wp_json_encode($widget_config) . ';';
        echo '</script>';
        echo '<script type="text/javascript" src="' . esc_url($script_url) . '" async defer></script>';
        echo "\n<!-- /SureFeedback Widget -->\n";
    }

    /**
     * Check if script injection should be skipped
     *
     * @return bool
     */
    private function should_skip_injection(): bool
    {
        global $wp_query;

        // Skip on admin pages
        if (is_admin()) {
            return true;
        }

        // Skip on login/register pages
        if (in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php'])) {
            return true;
        }

        // Skip on 404 pages
        if (is_404()) {
            return true;
        }

        // Skip if user doesn't have permission to leave feedback
        if (!$this->user_can_leave_feedback()) {
            return true;
        }

        // Skip on preview pages
        if (is_preview()) {
            return true;
        }

        // Skip on feed pages
        if (is_feed()) {
            return true;
        }

        // Check for page builder preview modes
        if ($this->is_page_builder_preview()) {
            return true;
        }

        // Allow filtering
        return apply_filters('surefeedback_skip_widget_injection', false);
    }

    /**
     * Check if current page is in page builder preview mode
     *
     * @return bool
     */
    private function is_page_builder_preview(): bool
    {
        // Elementor
        if (isset($_GET['elementor-preview'])) {
            return true;
        }

        // Divi Builder
        if (isset($_GET['et_fb']) || isset($_GET['et_pb_preview'])) {
            return true;
        }

        // Beaver Builder
        if (isset($_GET['fl_builder'])) {
            return true;
        }

        // Fusion Builder (Avada)
        if (isset($_GET['builder']) || isset($_GET['fb-edit'])) {
            return true;
        }

        // Oxygen Builder
        if (isset($_GET['ct_builder'])) {
            return true;
        }

        // Visual Composer
        if (isset($_GET['vc_editable'])) {
            return true;
        }

        // Gutenberg full site editing
        if (isset($_GET['postType']) && $_GET['postType'] === 'wp_template') {
            return true;
        }

        return false;
    }

    /**
     * Check if current user can leave feedback
     *
     * @return bool
     */
    private function user_can_leave_feedback(): bool
    {
        // Get allowed roles from settings
        $allowed_roles = get_option('surefeedback_allowed_roles', ['administrator', 'editor']);
        
        // Check for guest access
        $guest_comments = get_option('surefeedback_guest_comments', false);
        
        if (!is_user_logged_in()) {
            return $guest_comments;
        }

        $user = wp_get_current_user();
        
        // Check if user has any of the allowed roles
        $user_roles = $user->roles;
        return !empty(array_intersect($user_roles, $allowed_roles));
    }

    /**
     * Get widget settings
     *
     * @return array
     */
    private function get_widget_settings(): array
    {
        return [
            'enabled' => get_option('surefeedback_widget_enabled', true),
            'position' => get_option('surefeedback_widget_position', 'bottom-right'),
            'theme' => get_option('surefeedback_widget_theme', 'light'),
            'trigger_mode' => get_option('surefeedback_trigger_mode', 'manual'),
            'auto_show_delay' => get_option('surefeedback_auto_show_delay', 5000),
            'show_on_mobile' => get_option('surefeedback_show_on_mobile', true),
            'show_user_avatar' => get_option('surefeedback_show_user_avatar', true),
            'require_name' => get_option('surefeedback_require_name', false),
            'require_email' => get_option('surefeedback_require_email', false),
            'allow_file_upload' => get_option('surefeedback_allow_file_upload', true),
            'max_file_size' => get_option('surefeedback_max_file_size', 5242880), // 5MB
            'allowed_file_types' => get_option('surefeedback_allowed_file_types', ['jpg', 'png', 'gif', 'pdf'])
        ];
    }

    /**
     * Get current user data for widget
     *
     * @return array
     */
    private function get_current_user_data(): array
    {
        if (!is_user_logged_in()) {
            return [
                'logged_in' => false,
                'can_comment' => get_option('surefeedback_guest_comments', false)
            ];
        }

        $user = wp_get_current_user();
        
        return [
            'logged_in' => true,
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => get_avatar_url($user->ID, ['size' => 32]),
            'roles' => $user->roles,
            'can_comment' => $this->user_can_leave_feedback()
        ];
    }

    /**
     * Get current page data for widget
     *
     * @return array
     */
    private function get_current_page_data(): array
    {
        global $wp_query;

        $page_data = [
            'url' => home_url($_SERVER['REQUEST_URI']),
            'title' => wp_get_document_title(),
            'type' => 'unknown'
        ];

        if (is_front_page()) {
            $page_data['type'] = 'front_page';
            $page_data['id'] = get_option('page_on_front');
        } elseif (is_home()) {
            $page_data['type'] = 'blog_home';
            $page_data['id'] = get_option('page_for_posts');
        } elseif (is_singular()) {
            $page_data['type'] = get_post_type();
            $page_data['id'] = get_the_ID();
            $page_data['title'] = get_the_title();
        } elseif (is_category()) {
            $page_data['type'] = 'category';
            $page_data['id'] = get_queried_object_id();
            $page_data['title'] = single_cat_title('', false);
        } elseif (is_tag()) {
            $page_data['type'] = 'tag';
            $page_data['id'] = get_queried_object_id();
            $page_data['title'] = single_tag_title('', false);
        } elseif (is_archive()) {
            $page_data['type'] = 'archive';
            $page_data['title'] = get_the_archive_title();
        } elseif (is_search()) {
            $page_data['type'] = 'search';
            $page_data['title'] = 'Search Results for: ' . get_search_query();
        }

        return $page_data;
    }

    /**
     * AJAX handler for widget configuration
     *
     * @return void
     */
    public function ajax_widget_config(): void
    {
        // Allow public access for widget configuration
        $config = [
            'enabled' => get_option('surefeedback_widget_enabled', true),
            'connection_status' => get_option('surefeedback_connection_status', 'disconnected'),
            'settings' => $this->get_widget_settings(),
            'user' => $this->get_current_user_data(),
            'page' => $this->get_current_page_data()
        ];

        wp_send_json_success($config);
    }

    /**
     * Get widget status for admin
     *
     * @return array
     */
    public function get_widget_status(): array
    {
        $connection_status = get_option('surefeedback_connection_status', 'disconnected');
        $widget_enabled = get_option('surefeedback_widget_enabled', true);
        $site_id = get_option('surefeedback_id');
        $script_url = get_option('surefeedback_script_url');

        $status = [
            'active' => false,
            'connected' => $connection_status === 'connected',
            'enabled' => $widget_enabled,
            'configured' => !empty($site_id),
            'script_loaded' => !empty($script_url),
            'issues' => []
        ];

        // Check for issues
        if (!$status['connected']) {
            $status['issues'][] = 'Not connected to parent site';
        }

        if (!$status['enabled']) {
            $status['issues'][] = 'Widget is disabled';
        }

        if (!$status['configured']) {
            $status['issues'][] = 'Site ID not configured';
        }

        if (!$status['script_loaded']) {
            $status['issues'][] = 'Widget script URL not set';
        }

        $status['active'] = $status['connected'] && $status['enabled'] && $status['configured'];

        return $status;
    }

    /**
     * Test widget functionality
     *
     * @return array
     */
    public function test_widget(): array
    {
        $status = $this->get_widget_status();
        
        if (!$status['active']) {
            return [
                'success' => false,
                'message' => 'Widget is not active',
                'issues' => $status['issues']
            ];
        }

        // Test script URL accessibility
        $script_url = get_option('surefeedback_script_url');
        if (!empty($script_url)) {
            $response = wp_remote_head($script_url, ['timeout' => 10]);
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => 'Widget script is not accessible: ' . $response->get_error_message()
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return [
                    'success' => false,
                    'message' => "Widget script returned HTTP {$response_code}"
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Widget is functioning correctly',
            'status' => $status
        ];
    }
}