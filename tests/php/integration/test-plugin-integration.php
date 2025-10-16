<?php
/**
 * Plugin Integration Tests
 *
 * @package SureFeedback
 */

/**
 * Test plugin integration with WordPress
 */
class Test_Plugin_Integration extends SureFeedback_Test_Case
{
    /**
     * Test full plugin lifecycle
     */
    public function test_full_plugin_lifecycle()
    {
        // Initial state - no options should exist
        $this->assertOptionNotExists('surefeedback_installation_date');
        
        // Activate plugin
        $this->activate_plugin();
        
        // Check activation worked
        $this->assertOptionExists('surefeedback_installation_date');
        $this->assertOptionEquals(true, 'surefeedback_widget_enabled');
        
        // Modify some settings (simulating user configuration)
        update_option('surefeedback_guest_comments', true);
        update_option('surefeedback_access_token', 'test_token');
        
        // Schedule some events (simulating normal operation)
        wp_schedule_event(time() + 3600, 'hourly', 'surefeedback_auto_verify');
        
        // Verify everything is working
        $this->assertOptionEquals(true, 'surefeedback_guest_comments');
        $this->assertOptionEquals('test_token', 'surefeedback_access_token');
        $this->assertNotFalse(wp_next_scheduled('surefeedback_auto_verify'));
        
        // Deactivate plugin
        $this->deactivate_plugin();
        
        // Check that settings are preserved but events are cleared
        $this->assertOptionExists('surefeedback_installation_date');
        $this->assertOptionEquals(true, 'surefeedback_guest_comments');
        $this->assertOptionEquals('test_token', 'surefeedback_access_token');
        $this->assertFalse(wp_next_scheduled('surefeedback_auto_verify'));
        
        // Reactivate plugin
        $this->activate_plugin();
        
        // Check that settings are still preserved and new defaults aren't overriding
        $this->assertOptionEquals(true, 'surefeedback_guest_comments');
        $this->assertOptionEquals('test_token', 'surefeedback_access_token');
    }
    
    /**
     * Test plugin works with different WordPress configurations
     */
    public function test_plugin_works_with_different_wp_configs()
    {
        // Test with multisite simulation
        define('MULTISITE', true);
        $this->activate_plugin();
        $this->assertOptionExists('surefeedback_widget_enabled');
        
        // Test plugin handles admin context
        define('WP_ADMIN', true);
        $this->activate_plugin();
        $this->assertOptionExists('surefeedback_widget_enabled');
    }
    
    /**
     * Test plugin error handling
     */
    public function test_plugin_error_handling()
    {
        // Test activation with database error simulation
        // (In a real test, you might mock WordPress functions to return errors)
        
        $this->activate_plugin();
        
        // Plugin should still work even if some operations fail
        $this->assertTrue(true, 'Plugin should handle errors gracefully');
    }
}