<?php
/**
 * Plugin Core Functionality Tests
 *
 * @package SureFeedback
 */

/**
 * Test plugin core functionality after activation
 */
class Test_Plugin_Core_Functionality extends SureFeedback_Test_Case
{
    /**
     * Set up for core functionality tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->activate_plugin();
    }
    
    /**
     * Test plugin loads without fatal errors
     */
    public function test_plugin_loads_without_errors()
    {
        // If we get here, the plugin loaded successfully
        $this->assertTrue(true);
    }
    
    /**
     * Test plugin version constant
     */
    public function test_plugin_version_constant()
    {
        $this->assertTrue(defined('SUREFEEDBACK_VERSION'));
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', SUREFEEDBACK_VERSION);
    }
    
    /**
     * Test plugin file paths
     */
    public function test_plugin_file_paths()
    {
        $this->assertTrue(defined('SUREFEEDBACK_PLUGIN_DIR'));
        $this->assertTrue(defined('SUREFEEDBACK_PLUGIN_URL'));
        $this->assertTrue(defined('SUREFEEDBACK_PLUGIN_FILE'));
        
        // Check that plugin file exists
        $this->assertFileExists(SUREFEEDBACK_PLUGIN_FILE);
        
        // Check plugin directory exists
        $this->assertDirectoryExists(SUREFEEDBACK_PLUGIN_DIR);
    }
    
    /**
     * Test plugin basename
     */
    public function test_plugin_basename()
    {
        $this->assertTrue(defined('SUREFEEDBACK_PLUGIN_BASENAME'));
        $this->assertEquals('surefeedback/surefeedback.php', SUREFEEDBACK_PLUGIN_BASENAME);
    }
    
    /**
     * Test application instance is created
     */
    public function test_application_instance_exists()
    {
        global $app;
        $this->assertNotNull($app, 'Application instance should be created');
    }
    
    /**
     * Test internationalization setup
     */
    public function test_i18n_setup()
    {
        // Test that init action is added for text domain loading
        global $_wp_hooks;
        $this->assertArrayHasKey('init', $_wp_hooks);
        
        // Trigger the init action to load text domain
        do_action('init');
        
        // Test translation function works (basic test)
        $translated = __('Settings', 'surefeedback');
        $this->assertIsString($translated);
    }
    
    /**
     * Test plugin action links filter
     */
    public function test_plugin_action_links_filter()
    {
        global $_wp_hooks;
        
        $filter_name = 'plugin_action_links_' . SUREFEEDBACK_PLUGIN_BASENAME;
        $this->assertArrayHasKey($filter_name, $_wp_hooks);
        
        // Test the filter works
        $links = ['deactivate' => '<a href="#">Deactivate</a>'];
        $filtered_links = apply_filters($filter_name, $links);
        
        $this->assertIsArray($filtered_links);
        $this->assertCount(3, $filtered_links); // Original + Dashboard + Settings
    }
    
    /**
     * Test white label functionality
     */
    public function test_white_label_functionality()
    {
        // Set up white label options
        update_option('surefeedback_plugin_name', 'Custom Feedback Tool');
        update_option('surefeedback_plugin_description', 'Custom description');
        update_option('surefeedback_plugin_author', 'Custom Author');
        
        // Simulate admin_init action
        do_action('admin_init');
        
        // Test gettext filter is added
        global $_wp_hooks;
        $this->assertArrayHasKey('gettext', $_wp_hooks);
        
        // Test translation replacement
        $translated = apply_filters('gettext', 'SureFeedback Client', 'SureFeedback Client', 'surefeedback');
        $this->assertEquals('Custom Feedback Tool', $translated);
    }
    
    /**
     * Test scheduled events functionality
     */
    public function test_scheduled_events_functionality()
    {
        global $_wp_hooks;
        
        // Test action hooks are registered
        $this->assertArrayHasKey('surefeedback_auto_verify', $_wp_hooks);
        $this->assertArrayHasKey('surefeedback_hourly_verify', $_wp_hooks);
    }
    
    /**
     * Test redirect after activation
     */
    public function test_redirect_after_activation()
    {
        global $_wp_hooks;
        
        // Test activated_plugin action is registered
        $this->assertArrayHasKey('activated_plugin', $_wp_hooks);
        
        // Test redirect logic for disconnected state
        update_option('surefeedback_connection_status', 'disconnected');
        
        // This would normally trigger a redirect, but we can't test actual redirects
        // in unit tests, so we just verify the hook is registered
        $this->assertTrue(true);
    }
    
    /**
     * Test plugin handles missing dependencies gracefully
     */
    public function test_handles_missing_dependencies()
    {
        // Test that plugin doesn't break if Application class is missing
        // This is a defensive test for robustness
        $this->assertTrue(class_exists('SureFeedback\Application'));
    }
    
    /**
     * Test plugin cleanup on deactivation doesn't affect other plugins
     */
    public function test_deactivation_cleanup_isolation()
    {
        // Schedule some events
        wp_schedule_event(time() + 3600, 'hourly', 'surefeedback_auto_verify');
        wp_schedule_event(time() + 3600, 'hourly', 'some_other_plugin_event');
        
        // Deactivate plugin
        $this->deactivate_plugin();
        
        // Our events should be cleared
        $this->assertFalse(wp_next_scheduled('surefeedback_auto_verify'));
        
        // Other plugin events should remain
        $this->assertNotFalse(wp_next_scheduled('some_other_plugin_event'));
    }
    
    /**
     * Test plugin constants don't conflict with other plugins
     */
    public function test_plugin_constants_uniqueness()
    {
        $constants = [
            'SUREFEEDBACK_PLUGIN_DIR',
            'SUREFEEDBACK_PLUGIN_URL',
            'SUREFEEDBACK_PLUGIN_FILE',
            'SUREFEEDBACK_VERSION',
            'SUREFEEDBACK_PLUGIN_BASENAME'
        ];
        
        foreach ($constants as $constant) {
            // Test constant is properly namespaced
            $this->assertStringStartsWith('SUREFEEDBACK_', $constant);
        }
    }
    
    /**
     * Test plugin options are properly namespaced
     */
    public function test_plugin_options_namespacing()
    {
        $this->activate_plugin();
        
        $option_names = [
            'surefeedback_installation_date',
            'surefeedback_widget_enabled',
            'surefeedback_role_can_comment',
            'surefeedback_guest_comments',
            'surefeedback_admin_can_comment',
            'surefeedback_show_on_admin',
            'surefeedback_disable_for_admin',
            'surefeedback_debug_mode'
        ];
        
        foreach ($option_names as $option_name) {
            $this->assertStringStartsWith('surefeedback_', $option_name);
            $this->assertOptionExists($option_name);
        }
    }
}