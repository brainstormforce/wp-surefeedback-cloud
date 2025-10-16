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
            'surefeedback_widget_enabled',
            'surefeedback_role_can_comment',
            'surefeedback_guest_comments',
            'surefeedback_admin_can_comment',
            'surefeedback_show_on_admin',
            'surefeedback_disable_for_admin',
            'surefeedback_debug_mode',
            'surefeedback_connection_status',
            'surefeedback_access_token',
            'surefeedback_signature',
            'surefeedback_plugin_name',
            'surefeedback_plugin_description',
            'surefeedback_plugin_author',
            'surefeedback_plugin_author_url',
            'surefeedback_plugin_link'
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
        return [
            'surefeedback_widget_enabled' => true,
            'surefeedback_role_can_comment' => ['administrator'],
            'surefeedback_guest_comments' => false,
            'surefeedback_admin_can_comment' => true,
            'surefeedback_show_on_admin' => false,
            'surefeedback_disable_for_admin' => false,
            'surefeedback_debug_mode' => false
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