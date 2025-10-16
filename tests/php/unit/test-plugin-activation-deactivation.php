<?php
/**
 * Plugin Activation and Deactivation Tests
 *
 * @package SureFeedback
 */

/**
 * Test plugin activation and deactivation functionality
 */
class Test_Plugin_Activation_Deactivation extends SureFeedback_Test_Case
{
    /**
     * Test plugin activation sets correct default options
     */
    public function test_plugin_activation_sets_default_options()
    {
        // Ensure options don't exist before activation
        $this->assertOptionNotExists('surefeedback_installation_date');
        $this->assertOptionNotExists('surefeedback_widget_enabled');
        
        // Trigger activation
        $this->activate_plugin();
        
        // Check that installation date is set
        $this->assertOptionExists('surefeedback_installation_date');
        $installation_date = get_option('surefeedback_installation_date');
        $this->assertNotEmpty($installation_date);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $installation_date);
        
        // Check all default options are set correctly
        $expected_defaults = $this->get_default_options();
        foreach ($expected_defaults as $option => $expected_value) {
            $this->assertOptionEquals($expected_value, $option);
        }
    }
    
    /**
     * Test plugin activation doesn't override existing options
     */
    public function test_plugin_activation_preserves_existing_options()
    {
        // Set some existing options
        update_option('surefeedback_widget_enabled', false);
        update_option('surefeedback_guest_comments', true);
        update_option('surefeedback_role_can_comment', ['editor', 'author']);
        
        // Trigger activation
        $this->activate_plugin();
        
        // Check that existing options are preserved
        $this->assertOptionEquals(false, 'surefeedback_widget_enabled');
        $this->assertOptionEquals(true, 'surefeedback_guest_comments');
        $this->assertOptionEquals(['editor', 'author'], 'surefeedback_role_can_comment');
        
        // Check that missing options get default values
        $this->assertOptionEquals(true, 'surefeedback_admin_can_comment');
        $this->assertOptionEquals(false, 'surefeedback_show_on_admin');
    }
    
    /**
     * Test installation date is only set once
     */
    public function test_installation_date_set_only_once()
    {
        // First activation
        $this->activate_plugin();
        $first_installation_date = get_option('surefeedback_installation_date');
        
        // Wait a moment to ensure different timestamp
        sleep(1);
        
        // Second activation (simulate reactivation)
        $this->activate_plugin();
        $second_installation_date = get_option('surefeedback_installation_date');
        
        // Installation date should remain the same
        $this->assertEquals($first_installation_date, $second_installation_date);
    }
    
    /**
     * Test plugin deactivation clears scheduled events
     */
    public function test_plugin_deactivation_clears_scheduled_events()
    {
        // Setup some scheduled events (simulate them being scheduled)
        wp_schedule_event(time() + 3600, 'hourly', 'surefeedback_auto_verify');
        wp_schedule_event(time() + 3600, 'hourly', 'surefeedback_hourly_verify');
        
        // Verify events are scheduled
        $this->assertNotFalse(wp_next_scheduled('surefeedback_auto_verify'));
        $this->assertNotFalse(wp_next_scheduled('surefeedback_hourly_verify'));
        
        // Trigger deactivation
        $this->deactivate_plugin();
        
        // Verify events are cleared
        $this->assertFalse(wp_next_scheduled('surefeedback_auto_verify'));
        $this->assertFalse(wp_next_scheduled('surefeedback_hourly_verify'));
    }
    
    /**
     * Test plugin deactivation doesn't remove options
     */
    public function test_plugin_deactivation_preserves_options()
    {
        // Activate plugin to set up options
        $this->activate_plugin();
        
        // Verify options exist
        $this->assertOptionExists('surefeedback_widget_enabled');
        $this->assertOptionExists('surefeedback_installation_date');
        
        // Add some custom options
        update_option('surefeedback_access_token', 'test_token_123');
        update_option('surefeedback_connection_status', 'connected');
        
        // Trigger deactivation
        $this->deactivate_plugin();
        
        // Verify options still exist after deactivation
        $this->assertOptionExists('surefeedback_widget_enabled');
        $this->assertOptionExists('surefeedback_installation_date');
        $this->assertOptionEquals('test_token_123', 'surefeedback_access_token');
        $this->assertOptionEquals('connected', 'surefeedback_connection_status');
    }
    
    /**
     * Test multiple activation/deactivation cycles
     */
    public function test_multiple_activation_deactivation_cycles()
    {
        // First cycle
        $this->activate_plugin();
        $first_installation_date = get_option('surefeedback_installation_date');
        $this->assertOptionExists('surefeedback_widget_enabled');
        
        $this->deactivate_plugin();
        $this->assertOptionExists('surefeedback_widget_enabled'); // Should still exist
        
        // Second cycle
        $this->activate_plugin();
        $second_installation_date = get_option('surefeedback_installation_date');
        $this->assertEquals($first_installation_date, $second_installation_date);
        
        $this->deactivate_plugin();
        
        // Third cycle with modified options
        update_option('surefeedback_debug_mode', true);
        $this->activate_plugin();
        $this->assertOptionEquals(true, 'surefeedback_debug_mode'); // Should preserve existing value
        $this->assertOptionEquals(true, 'surefeedback_widget_enabled'); // Should have default for new options
    }
    
    /**
     * Test activation hook is properly registered
     */
    public function test_activation_hook_is_registered()
    {
        global $_wp_hooks;
        
        // Check that activation hook is registered
        $expected_hook = 'activate_' . SUREFEEDBACK_PLUGIN_BASENAME;
        $this->assertArrayHasKey($expected_hook, $_wp_hooks);
        $this->assertNotEmpty($_wp_hooks[$expected_hook]);
    }
    
    /**
     * Test deactivation hook is properly registered
     */
    public function test_deactivation_hook_is_registered()
    {
        global $_wp_hooks;
        
        // Check that deactivation hook is registered
        $expected_hook = 'deactivate_' . SUREFEEDBACK_PLUGIN_BASENAME;
        $this->assertArrayHasKey($expected_hook, $_wp_hooks);
        $this->assertNotEmpty($_wp_hooks[$expected_hook]);
    }
    
    /**
     * Test plugin constants are defined
     */
    public function test_plugin_constants_are_defined()
    {
        $required_constants = [
            'SUREFEEDBACK_PLUGIN_DIR',
            'SUREFEEDBACK_PLUGIN_URL',
            'SUREFEEDBACK_PLUGIN_FILE',
            'SUREFEEDBACK_VERSION',
            'SUREFEEDBACK_PLUGIN_BASENAME'
        ];
        
        foreach ($required_constants as $constant) {
            $this->assertTrue(defined($constant), "Constant {$constant} should be defined");
            $this->assertNotEmpty(constant($constant), "Constant {$constant} should not be empty");
        }
    }
    
    /**
     * Test default options structure and types
     */
    public function test_default_options_structure()
    {
        $this->activate_plugin();
        
        // Test boolean options
        $boolean_options = [
            'surefeedback_widget_enabled',
            'surefeedback_guest_comments',
            'surefeedback_admin_can_comment',
            'surefeedback_show_on_admin',
            'surefeedback_disable_for_admin',
            'surefeedback_debug_mode'
        ];
        
        foreach ($boolean_options as $option) {
            $value = get_option($option);
            $this->assertIsBool($value, "Option {$option} should be boolean");
        }
        
        // Test array options
        $role_can_comment = get_option('surefeedback_role_can_comment');
        $this->assertIsArray($role_can_comment, 'surefeedback_role_can_comment should be an array');
        $this->assertContains('administrator', $role_can_comment, 'Should contain administrator role by default');
        
        // Test string options
        $installation_date = get_option('surefeedback_installation_date');
        $this->assertIsString($installation_date, 'Installation date should be a string');
    }
}