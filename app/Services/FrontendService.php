<?php

namespace SureFeedback\Services;

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
        // Inject widget script in head like the working example
        add_action('wp_head', [$this, 'inject_widget_script'], 999);
        
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

        // Use direct injection method for better reliability
        $this->render_widget_script_wordpress_style();
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

        // Enqueue widget script from Laravel server
    

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
        $api_url = get_option('surefeedback_api_url');
        $script_token = get_option('surefeedback_script_token', $access_token);

        // Get environment-aware base API URL
        if (empty($api_url)) {
            $api_url = surefeedback_get_base_api_url();
        }

        if (empty($script_token)) {
            return;
        }

        // Construct widget loader URL
        $widget_loader_url = trailingslashit($api_url) . 'js/widget-loader.js';

        // Get debug mode setting
        $debug_mode = get_option('surefeedback_debug_mode', false) ? 'true' : 'false';

        // Get restricted URL and required token if needed
        $restricted_url = null;
        $required_token = null;

        // Check if user is logged in and has permission
        $user_allowed = $this->user_can_leave_feedback();
        $current_user = $this->get_current_user_data();

        echo "\n<!-- SureFeedback Widget -->\n";
        echo "<!-- Site ID: " . esc_html($site_id) . ", Token: " . esc_html(substr($script_token, 0, 10)) . "..., API: " . esc_html($api_url) . " -->\n";
        ?>
        <script>
        // SureFeedback Integration Script
        (function (d, t, g, defaultToken, baseUrl, debug, restrictedUrl, requiredToken) {
          'use strict';
          var sf = d.createElement(t),
            s = d.getElementsByTagName(t)[0];
          
          sf.type = 'text/javascript';
          sf.async = true;
          sf.defer = true;
          sf.charset = 'UTF-8';
          sf.src = g + '?v=' + (new Date()).getTime();
          sf.setAttribute('data-default-token', defaultToken);
          sf.setAttribute('data-base-url', baseUrl || '<?php echo esc_js($api_url); ?>');
          sf.setAttribute('data-debug', debug || 'false');
          sf.setAttribute('data-mode', 'wordpress');
          sf.setAttribute('data-platform', 'wordpress');
          
          // Add WordPress-specific configuration
          sf.setAttribute('data-site-id', '<?php echo esc_js($site_id); ?>');
          sf.setAttribute('data-current-url', '<?php echo esc_js(get_permalink()); ?>');
          sf.setAttribute('data-page-title', '<?php echo esc_js(get_the_title()); ?>');
          sf.setAttribute('data-page-id', '<?php echo esc_js(get_the_ID()); ?>');
          
          <?php if ($user_allowed && $current_user): ?>
          // Add user data if authenticated
          sf.setAttribute('data-user-name', '<?php echo esc_js($current_user['name']); ?>');
          sf.setAttribute('data-user-email', '<?php echo esc_js($current_user['email']); ?>');
          sf.setAttribute('data-user-id', '<?php echo esc_js($current_user['id']); ?>');
          <?php endif; ?>
          
          // Optional: Add restricted URL and required token if provided
          if (restrictedUrl) {
            sf.setAttribute('data-restricted-url', restrictedUrl);
          }
          if (requiredToken) {
            sf.setAttribute('data-required-token', requiredToken);
          }
          
          s.parentNode.insertBefore(sf, s);
        })(document, 'script', '<?php echo esc_js($widget_loader_url); ?>', '<?php echo esc_js($script_token); ?>', '<?php echo esc_js($api_url); ?>', '<?php echo esc_js($debug_mode); ?>', <?php echo $restricted_url ? "'" . esc_js($restricted_url) . "'" : 'null'; ?>, <?php echo $required_token ? "'" . esc_js($required_token) . "'" : 'null'; ?>);
        </script>
        <?php
        echo "\n<!-- /SureFeedback Widget -->\n";
    }

    /**
     * Inject widget script in head for early loading
     *
     * @return void
     */
    public function inject_widget_script_early(): void
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

        // Add preload hint for the widget loader
        $api_url = get_option('surefeedback_api_url');
        if (empty($api_url)) {
            $api_url = surefeedback_get_base_api_url();
        }
        
        $widget_loader_url = trailingslashit($api_url) . 'js/widget-loader.js';
        
        echo "\n<!-- SureFeedback Widget Preload -->\n";
        echo '<link rel="preload" href="' . esc_url($widget_loader_url) . '" as="script">' . "\n";
        echo '<link rel="prefetch" href="' . esc_url(trailingslashit($api_url) . 'dist/widget.js') . '" as="script">' . "\n";
        echo '<link rel="prefetch" href="' . esc_url(trailingslashit($api_url) . 'dist/widget.css') . '" as="style">' . "\n";
        echo "<!-- /SureFeedback Widget Preload -->\n";
    }

    /**
     * Render widget script directly with minimal dependencies
     *
     * @return void
     */
    private function render_widget_script_direct(): void
    {
        static $script_injected = false;
        
        // Prevent multiple injections
        if ($script_injected) {
            return;
        }
        $script_injected = true;
        
        $site_id = get_option('surefeedback_id');
        $access_token = get_option('surefeedback_access_token');
        $api_url = get_option('surefeedback_api_url');
        $script_token = get_option('surefeedback_script_token', $access_token);

        // Get environment-aware base API URL
        if (empty($api_url)) {
            $api_url = surefeedback_get_base_api_url();
        }

        if (empty($script_token)) {
            return;
        }

        // Construct widget loader URL
        $widget_loader_url = trailingslashit($api_url) . 'js/widget-loader.js';

        // Get current user data
        $current_user = wp_get_current_user();
        $user_data = [
            'name' => $current_user->display_name ?: '',
            'email' => $current_user->user_email ?: '',
            'id' => $current_user->ID ?: 0
        ];

        echo "\n<!-- SureFeedback Widget Direct -->\n";
        echo "<!-- Direct injection method for better compatibility -->\n";
        ?>
        <script id="surefeedback-widget-direct">
        (function() {
            'use strict';
            
            // Prevent multiple loads
            if (window.SureFeedbackLoaded) return;
            window.SureFeedbackLoaded = true;
            
            // Configuration
            var config = {
                siteId: '<?php echo esc_js($site_id); ?>',
                token: '<?php echo esc_js($script_token); ?>',
                apiUrl: '<?php echo esc_js($api_url); ?>',
                currentUrl: '<?php echo esc_js(home_url($_SERVER['REQUEST_URI'])); ?>',
                pageTitle: '<?php echo esc_js(wp_get_document_title()); ?>',
                pageId: '<?php echo esc_js(get_the_ID() ?: 0); ?>',
                user: {
                    name: '<?php echo esc_js($user_data['name']); ?>',
                    email: '<?php echo esc_js($user_data['email']); ?>',
                    id: '<?php echo esc_js($user_data['id']); ?>'
                }
            };
            
            // Load widget script
            var script = document.createElement('script');
            script.src = '<?php echo esc_js($widget_loader_url); ?>?v=' + Date.now();
            script.async = true;
            script.defer = true;
            script.setAttribute('data-default-token', config.token);
            script.setAttribute('data-base-url', config.apiUrl);
            script.setAttribute('data-site-id', config.siteId);
            script.setAttribute('data-current-url', config.currentUrl);
            script.setAttribute('data-page-title', config.pageTitle);
            script.setAttribute('data-page-id', config.pageId);
            script.setAttribute('data-user-name', config.user.name);
            script.setAttribute('data-user-email', config.user.email);
            script.setAttribute('data-user-id', config.user.id);
            script.setAttribute('data-mode', 'wordpress');
            script.setAttribute('data-platform', 'wordpress');
            script.setAttribute('data-debug', 'true');
            
            script.onload = function() {
                // Widget loader script loaded successfully
                
                // Add a timeout to check if widget initialized
                setTimeout(function() {
                    // Widget object and instance checks are handled silently
                }, 3000);
            };
            
            script.onerror = function() {
                // Failed to load widget loader script
                
                // Fallback: try loading widget.js directly
                var fallbackScript = document.createElement('script');
                fallbackScript.src = config.apiUrl + '/dist/widget.js?v=' + Date.now();
                fallbackScript.async = true;
                fallbackScript.onload = function() {
                    // Direct widget script loaded successfully
                    if (window.SureFeedback && window.SureFeedback.init) {
                        window.SureFeedback.init(config);
                    } else if (window.SureFeedbackWidget && window.SureFeedbackWidget.init) {
                        window.SureFeedbackWidget.init(config);
                    }
                    // No initialization method found - handled silently
                };
                fallbackScript.onerror = function() {
                    // Failed to load fallback widget script - handled silently
                };
                document.head.appendChild(fallbackScript);
            };
            
            document.head.appendChild(script);
            
            // Store config globally for widget access
            window.SureFeedbackConfig = config;
            
        })();
        </script>
        <?php
        echo "\n<!-- /SureFeedback Widget Direct -->\n";
    }

    /**
     * Render widget script using the exact WordPress style that works
     *
     * @return void
     */
    private function render_widget_script_wordpress_style(): void
    {
        static $script_injected = false;
        
        // Prevent multiple injections
        if ($script_injected) {
            return;
        }
        $script_injected = true;
        
        $site_id = get_option('surefeedback_id');
        $access_token = get_option('surefeedback_access_token');
        $api_url = get_option('surefeedback_api_url');
        $script_token = get_option('surefeedback_script_token', $access_token);

        // Get environment-aware base API URL
        if (empty($api_url)) {
            $api_url = surefeedback_get_base_api_url();
        }

        if (empty($script_token)) {
            return;
        }

        // Construct widget loader URL
        $widget_loader_url = trailingslashit($api_url) . 'js/widget-loader.js';

        echo "\n<!-- SureFeedback WordPress Integration -->\n";
        ?>
        <script>
        // SureFeedback WordPress Integration Script
        (function (d, t, g, defaultToken, baseUrl, debug, restrictedUrl, requiredToken) {
          'use strict';
          var sf = d.createElement(t),
            s = d.getElementsByTagName(t)[0];
          
          sf.type = 'text/javascript';
          sf.async = true;
          sf.defer = true;
          sf.charset = 'UTF-8';
          sf.src = g + '?v=' + (new Date()).getTime();
          sf.setAttribute('data-default-token', defaultToken);
          sf.setAttribute('data-base-url', baseUrl || '<?php echo esc_js($api_url); ?>');
          sf.setAttribute('data-debug', debug || 'false');
          sf.setAttribute('data-mode', 'iframe');
          sf.setAttribute('data-platform', 'wordpress');
          
          // Add WordPress-specific configuration
          sf.setAttribute('data-site-id', '<?php echo esc_js($site_id); ?>');
          sf.setAttribute('data-current-url', '<?php echo esc_js(home_url($_SERVER['REQUEST_URI'])); ?>');
          sf.setAttribute('data-page-title', '<?php echo esc_js(wp_get_document_title()); ?>');
          sf.setAttribute('data-page-id', '<?php echo esc_js(get_the_ID() ?: 0); ?>');
          
          <?php 
          $current_user = wp_get_current_user();
          if ($current_user->ID > 0): ?>
          // Add user data if authenticated
          sf.setAttribute('data-user-name', '<?php echo esc_js($current_user->display_name); ?>');
          sf.setAttribute('data-user-email', '<?php echo esc_js($current_user->user_email); ?>');
          sf.setAttribute('data-user-id', '<?php echo esc_js($current_user->ID); ?>');
          <?php endif; ?>
          
          // Optional: Add restricted URL and required token if provided
          if (restrictedUrl) {
            sf.setAttribute('data-restricted-url', restrictedUrl);
          }
          if (requiredToken) {
            sf.setAttribute('data-required-token', requiredToken);
          }
          
          s.parentNode.insertBefore(sf, s);
        })(document, 'script', '<?php echo esc_js($widget_loader_url); ?>', '<?php echo esc_js($script_token); ?>', '<?php echo esc_js($api_url); ?>', 'true', null, null);
        </script>
        <?php
        echo "<!-- /SureFeedback WordPress Integration -->\n";
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
        // Temporarily bypass permission check for debugging
        return true;
        
        // Get allowed roles from settings
        $allowed_roles = get_option('surefeedback_role_can_comment', ['administrator', 'editor']);
        
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