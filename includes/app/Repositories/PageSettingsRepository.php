<?php

namespace SureFeedback\Repositories;

defined('ABSPATH') || exit;

/**
 * Page Settings Repository
 *
 * Handles page-specific widget visibility settings.
 *
 * @package SureFeedback\App\Repositories
 * @author Anurag Singh <anurags@bsf.io>
 */
class PageSettingsRepository extends BaseRepository
{
    /**
     * Get all pages with widget status
     *
     * @return array
     */
    public function getAllPagesWithStatus(): array
    {
        $pages = $this->getAllPages();
        $pageSettings = $this->getPageSettings();
        
        foreach ($pages as &$page) {
            $page['widget_enabled'] = $this->isWidgetEnabledForPage($page['id'], $pageSettings);
        }
        
        return $pages;
    }
    
    /**
     * Get all WordPress pages
     *
     * @return array
     */
    private function getAllPages(): array
    {
        $pages = [];
        
        // Get homepage
        $homepage_id = get_option('page_on_front');
        if ($homepage_id) {
            $pages[] = [
                'id' => 'home',
                'title' => __('Homepage', 'surefeedback'),
                'type' => 'home',
                'url' => home_url('/')
            ];
        }
        
        // Get blog page
        $blog_page_id = get_option('page_for_posts');
        if ($blog_page_id) {
            $pages[] = [
                'id' => 'blog',
                'title' => __('Blog Page', 'surefeedback'),
                'type' => 'blog',
                'url' => get_permalink($blog_page_id)
            ];
        }
        
        // Get all published pages
        $wp_pages = get_pages([
            'post_status' => 'publish',
            'number' => 1000,
            'sort_column' => 'post_title',
            'sort_order' => 'ASC'
        ]);
        
        foreach ($wp_pages as $wp_page) {
            $pages[] = [
                'id' => 'page_' . $wp_page->ID,
                'title' => $wp_page->post_title ?: __('(No Title)', 'surefeedback'),
                'type' => 'page',
                'url' => get_permalink($wp_page->ID),
                'post_id' => $wp_page->ID
            ];
        }
        
        // Get all published posts
        $wp_posts = get_posts([
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        foreach ($wp_posts as $wp_post) {
            $pages[] = [
                'id' => 'post_' . $wp_post->ID,
                'title' => $wp_post->post_title ?: __('(No Title)', 'surefeedback'),
                'type' => 'post',
                'url' => get_permalink($wp_post->ID),
                'post_id' => $wp_post->ID
            ];
        }
        
        // Get all custom post types
        $post_types = get_post_types([
            'public' => true,
            '_builtin' => false
        ], 'objects');
        
        foreach ($post_types as $post_type) {
            $cpt_posts = get_posts([
                'post_type' => $post_type->name,
                'post_status' => 'publish',
                'posts_per_page' => 50,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
            
            foreach ($cpt_posts as $cpt_post) {
                $pages[] = [
                    'id' => $post_type->name . '_' . $cpt_post->ID,
                    'title' => $cpt_post->post_title ?: __('(No Title)', 'surefeedback'),
                    'type' => $post_type->name,
                    'type_label' => $post_type->label,
                    'url' => get_permalink($cpt_post->ID),
                    'post_id' => $cpt_post->ID
                ];
            }
        }
        
        // Get archive pages
        $pages[] = [
            'id' => 'archive',
            'title' => __('All Archives', 'surefeedback'),
            'type' => 'archive',
            'url' => ''
        ];
        
        // Get search page
        $pages[] = [
            'id' => 'search',
            'title' => __('Search Results', 'surefeedback'),
            'type' => 'search',
            'url' => ''
        ];
        
        // Get 404 page
        $pages[] = [
            'id' => '404',
            'title' => __('404 Page', 'surefeedback'),
            'type' => '404',
            'url' => ''
        ];
        
        return $pages;
    }
    
    /**
     * Get page settings
     *
     * @return array
     */
    public function getPageSettings(): array
    {
        return (array) $this->getOption('page_widget_settings', []);
    }
    
    /**
     * Check if widget is enabled for a specific page
     *
     * @param string $page_id
     * @param array|null $settings
     * @return bool
     */
    public function isWidgetEnabledForPage(string $page_id, ?array $settings = null): bool
    {
        if ($settings === null) {
            $settings = $this->getPageSettings();
        }
        
        // If no settings exist, widget is enabled by default for all pages
        if (empty($settings)) {
            return true;
        }
        
        // Check if page is explicitly disabled
        if (isset($settings[$page_id]) && $settings[$page_id] === false) {
            return false;
        }
        
        // Default to enabled
        return true;
    }
    
    /**
     * Update page settings
     *
     * @param array $settings
     * @return bool
     */
    public function updatePageSettings(array $settings): bool
    {
        $sanitized_settings = [];
        
        foreach ($settings as $page_id => $enabled) {
            $sanitized_page_id = sanitize_text_field($page_id);
            $sanitized_settings[$sanitized_page_id] = (bool) $enabled;
        }
        
        return $this->setOption('page_widget_settings', $sanitized_settings);
    }
    
    /**
     * Enable widget for specific page
     *
     * @param string $page_id
     * @return bool
     */
    public function enableWidgetForPage(string $page_id): bool
    {
        $settings = $this->getPageSettings();
        $settings[sanitize_text_field($page_id)] = true;
        return $this->updatePageSettings($settings);
    }
    
    /**
     * Disable widget for specific page
     *
     * @param string $page_id
     * @return bool
     */
    public function disableWidgetForPage(string $page_id): bool
    {
        $settings = $this->getPageSettings();
        $settings[sanitize_text_field($page_id)] = false;
        return $this->updatePageSettings($settings);
    }
    
    /**
     * Enable widget for all pages
     *
     * @return bool
     */
    public function enableWidgetForAllPages(): bool
    {
        return $this->setOption('page_widget_settings', []);
    }
    
    /**
     * Disable widget for all pages
     *
     * @return bool
     */
    public function disableWidgetForAllPages(): bool
    {
        $pages = $this->getAllPages();
        $settings = [];
        
        foreach ($pages as $page) {
            $settings[$page['id']] = false;
        }
        
        return $this->updatePageSettings($settings);
    }
    
    /**
     * Get current page ID based on WordPress query
     *
     * @return string|null
     */
    public function getCurrentPageId(): ?string
    {
        if (is_front_page()) {
            return 'home';
        }
        
        if (is_home()) {
            return 'blog';
        }
        
        if (is_singular()) {
            $post_type = get_post_type();
            $post_id = get_the_ID();
            
            if ($post_type === 'page') {
                return 'page_' . $post_id;
            } elseif ($post_type === 'post') {
                return 'post_' . $post_id;
            } else {
                return $post_type . '_' . $post_id;
            }
        }
        
        if (is_archive()) {
            return 'archive';
        }
        
        if (is_search()) {
            return 'search';
        }
        
        if (is_404()) {
            return '404';
        }
        
        return null;
    }
    
    /**
     * Check if widget should be displayed on current page
     *
     * @return bool
     */
    public function shouldDisplayWidgetOnCurrentPage(): bool
    {
        $current_page_id = $this->getCurrentPageId();
        
        if (!$current_page_id) {
            return true; // Default to showing widget if page type is unknown
        }
        
        return $this->isWidgetEnabledForPage($current_page_id);
    }
}
