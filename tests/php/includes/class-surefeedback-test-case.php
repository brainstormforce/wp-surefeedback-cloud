<?php
/**
 * Base Test Case for SureFeedback Plugin Tests
 *
 * @package SureFeedback
 */

/**
 * SureFeedback Test Case Base Class
 */
class SureFeedback_Test_Case extends WP_UnitTestCase
{
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset plugin options before each test
        $this->reset_plugin_options();
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void
    {
        // Clean up any test data
        $this->reset_plugin_options();
        
        parent::tearDown();
    }
    
    /**
     * Reset all plugin options to default state
     */
    protected function reset_plugin_options()
    {
        $plugin_options = [
            'surefeedback_installation_date',
            'surefeedback_roles',
            'surefeedback_connection_status',
            'surefeedback_access_token',
            'surefeedback_signature',
            'surefeedback_site_id',
            'surefeedback_parent_url',
        ];
        
        foreach ($plugin_options as $option) {
            delete_option($option);
        }
    }
    
    /**
     * Helper method to get default plugin options
     */
    protected function get_default_options()
    {
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }
        
        return [
            'surefeedback_roles' => array_keys($wp_roles->roles), // All roles enabled by default
            'surefeedback_admin_can_comment' => true,
            'surefeedback_show_on_admin' => false,
            'surefeedback_disable_for_admin' => false,
        ];
    }
    
    /**
     * Helper method to simulate plugin activation
     */
    protected function activate_plugin()
    {
        // Simulate the activation hook
        do_action('activate_' . SUREFEEDBACK_PLUGIN_BASENAME);
    }
    
    /**
     * Helper method to simulate plugin deactivation
     */
    protected function deactivate_plugin()
    {
        // Simulate the deactivation hook
        do_action('deactivate_' . SUREFEEDBACK_PLUGIN_BASENAME);
    }
    
    /**
     * Helper method to check if option exists and has expected value
     */
    protected function assertOptionEquals($expected, $option_name, $message = '')
    {
        $actual = get_option($option_name);
        $this->assertEquals($expected, $actual, $message ?: "Option {$option_name} does not match expected value");
    }
    
    /**
     * Helper method to check if option exists
     */
    protected function assertOptionExists($option_name, $message = '')
    {
        $this->assertNotFalse(get_option($option_name, false), $message ?: "Option {$option_name} should exist");
    }
    
    /**
     * Helper method to check if option does not exist
     */
    protected function assertOptionNotExists($option_name, $message = '')
    {
        $this->assertFalse(get_option($option_name, false), $message ?: "Option {$option_name} should not exist");
    }
}