<?php

namespace SureFeedback\Http\Controllers\Api;

use SureFeedback\Http\Controllers\Controller;
use SureFeedback\Http\Requests\DashboardStatsRequest;
use SureFeedback\Repositories\DashboardRepository;
use SureFeedback\Repositories\ConnectionRepository;
use SureFeedback\Repositories\SettingsRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Dashboard Controller
 *
 * Handles all dashboard-related API endpoints including
 * statistics, quick access data, and recent activity.
 *
 * @package SureFeedback\App\Http\Controllers\Api
 * @author Anurag Singh <anurags@bsf.io>
 */
class DashboardController extends Controller
{
    /**
     * Dashboard Repository
     *
     * @var DashboardRepository
     */
    protected $dashboardRepository;

    /**
     * Connection Repository
     *
     * @var ConnectionRepository
     */
    protected $connectionRepository;

    /**
     * Settings Repository
     *
     * @var SettingsRepository
     */
    protected $settingsRepository;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->dashboardRepository = new DashboardRepository();
        $this->connectionRepository = new ConnectionRepository();
        $this->settingsRepository = new SettingsRepository();
    }
    /**
     * Get dashboard statistics
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function stats(WP_REST_Request $request)
    {
        try {
            $this->validateNonce($request);
            $this->validateCapability('read');
            
            $period = sanitize_text_field($request->get_param('period') ?? '30');
            $stats = $this->calculateStats($period);
            
            $this->logInfo('Dashboard stats requested', ['period' => $period]);
            
            return $this->success($stats);
            
        } catch (\Exception $e) {
            $this->logError('Dashboard stats error: ' . $e->getMessage());
            return $this->error('Failed to retrieve dashboard statistics', 500);
        }
    }
    
    /**
     * Get quick access data
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function quickAccess(WP_REST_Request $request)
    {
        try {
            $this->validateNonce($request);
            $this->validateCapability('read');
            
            $quick_access_data = [
                'recent_comments' => $this->getRecentComments(5),
                'pending_feedback' => $this->getPendingFeedback(),
                'popular_pages' => $this->getPopularPages(5),
                'connection_status' => $this->getConnectionStatus(),
                'quick_stats' => $this->getQuickStats()
            ];
            
            return $this->success($quick_access_data);
            
        } catch (\Exception $e) {
            $this->logError('Quick access data error: ' . $e->getMessage());
            return $this->error('Failed to retrieve quick access data', 500);
        }
    }
    
    /**
     * Get recent activity
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function recentActivity(WP_REST_Request $request)
    {
        try {
            $this->validateNonce($request);
            $this->validateCapability('read');
            
            $limit = absint($request->get_param('limit') ?? 10);
            $limit = min(50, max(1, $limit)); // Ensure reasonable limits
            
            $activity = $this->getRecentActivity($limit);
            
            return $this->success([
                'activity' => $activity,
                'total' => count($activity),
                'limit' => $limit,
                'generated_at' => current_time('mysql')
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Recent activity error: ' . $e->getMessage());
            return $this->error('Failed to retrieve recent activity', 500);
        }
    }
    
    /**
     * Calculate dashboard statistics
     *
     * @param string $period
     * @return array
     */
    private function calculateStats(string $period): array
    {
        $days = $this->parsePeriod($period);
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get feedback statistics
        $total_feedback = $this->getTotalFeedback($start_date);
        $resolved_feedback = $this->getResolvedFeedback($start_date);
        $pending_feedback = $this->getPendingFeedbackCount($start_date);
        
        // Get page statistics
        $total_pages = $this->getTotalPages();
        $pages_with_feedback = $this->getPagesWithFeedback($start_date);
        
        // Get user statistics
        $active_users = $this->getActiveUsers($start_date);
        $total_comments = $this->getTotalComments($start_date);
        
        // Calculate metrics
        $resolution_rate = $total_feedback > 0 ? round(($resolved_feedback / $total_feedback) * 100, 1) : 0;
        $engagement_rate = $total_pages > 0 ? round(($pages_with_feedback / $total_pages) * 100, 1) : 0;
        
        return [
            'period' => $period,
            'period_days' => $days,
            'start_date' => $start_date,
            'feedback' => [
                'total' => $total_feedback,
                'resolved' => $resolved_feedback,
                'pending' => $pending_feedback,
                'resolution_rate' => $resolution_rate
            ],
            'pages' => [
                'total' => $total_pages,
                'with_feedback' => $pages_with_feedback,
                'engagement_rate' => $engagement_rate
            ],
            'users' => [
                'active' => $active_users,
                'total_comments' => $total_comments
            ],
            'trends' => $this->calculateTrends($days)
        ];
    }
    
    /**
     * Get recent comments
     *
     * @param int $limit
     * @return array
     */
    private function getRecentComments(int $limit): array
    {
        // This would typically query your feedback/comments table
        // For now, we'll return mock data structure
        return [
            [
                'id' => 1,
                'page_title' => 'Homepage',
                'page_url' => home_url(),
                'comment' => 'The header looks great but could use more contrast',
                'author' => 'John Doe',
                'created_at' => current_time('mysql'),
                'status' => 'pending'
            ]
            // Add more mock data as needed
        ];
    }
    
    /**
     * Get pending feedback count
     *
     * @return int
     */
    private function getPendingFeedback(): int
    {
        // Query your feedback table for pending items
        return get_option('surefeedback_pending_count', 0);
    }
    
    /**
     * Get popular pages
     *
     * @param int $limit
     * @return array
     */
    private function getPopularPages(int $limit): array
    {
        $pages = get_pages([
            'sort_column' => 'post_modified',
            'sort_order' => 'DESC',
            'number' => $limit,
            'post_status' => 'publish'
        ]);
        
        $popular_pages = [];
        foreach ($pages as $page) {
            $feedback_count = $this->getPageFeedbackCount($page->ID);
            
            $popular_pages[] = [
                'id' => $page->ID,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
                'feedback_count' => $feedback_count,
                'last_modified' => $page->post_modified
            ];
        }
        
        // Sort by feedback count
        usort($popular_pages, function ($a, $b) {
            return $b['feedback_count'] - $a['feedback_count'];
        });
        
        return array_slice($popular_pages, 0, $limit);
    }
    
    /**
     * Get connection status
     *
     * @return array
     */
    private function getConnectionStatus(): array
    {
        $connected = get_option('surefeedback_connected', false);
        $parent_url = get_option('surefeedback_parent_url', '');
        $last_check = get_option('surefeedback_last_connection_check', 0);
        
        $status = 'disconnected';
        if ($connected && !empty($parent_url)) {
            $status = 'connected';
            if ($last_check && (time() - $last_check) > 300) { // 5 minutes
                $status = 'stale';
            }
        }
        
        return [
            'status' => $status,
            'connected' => $connected,
            'parent_url' => $parent_url,
            'last_check' => $last_check ? date('Y-m-d H:i:s', $last_check) : null,
            'health_score' => $this->calculateConnectionHealth()
        ];
    }
    
    /**
     * Get quick statistics
     *
     * @return array
     */
    private function getQuickStats(): array
    {
        return [
            'total_feedback' => $this->getTotalFeedback(),
            'pending_feedback' => $this->getPendingFeedbackCount(),
            'total_pages' => $this->getTotalPages(),
            'plugin_version' => SUREFEEDBACK_VERSION,
            'last_activity' => $this->getLastActivity()
        ];
    }
    
    /**
     * Get recent activity
     *
     * @param int $limit
     * @return array
     */
    private function getRecentActivity(int $limit): array
    {
        $activity = [];
        
        // Get recent comments
        $recent_comments = $this->getRecentComments($limit);
        foreach ($recent_comments as $comment) {
            $activity[] = [
                'type' => 'comment',
                'title' => 'New comment on ' . $comment['page_title'],
                'description' => wp_trim_words($comment['comment'], 15),
                'author' => $comment['author'],
                'timestamp' => $comment['created_at'],
                'icon' => 'comment',
                'url' => $comment['page_url']
            ];
        }
        
        // Get recent connections
        $connection_date = get_option('surefeedback_connection_date', '');
        if (!empty($connection_date)) {
            $activity[] = [
                'type' => 'connection',
                'title' => 'Connected to parent site',
                'description' => 'Successfully connected to ' . get_option('surefeedback_parent_url', ''),
                'author' => 'System',
                'timestamp' => $connection_date,
                'icon' => 'link',
                'url' => admin_url('admin.php?page=surefeedback-settings')
            ];
        }
        
        // Sort by timestamp (newest first)
        usort($activity, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activity, 0, $limit);
    }
    
    /**
     * Parse period string to days
     *
     * @param string $period
     * @return int
     */
    private function parsePeriod(string $period): int
    {
        $periods = [
            '7' => 7,
            '30' => 30,
            '90' => 90,
            '365' => 365
        ];
        
        return $periods[$period] ?? 30;
    }
    
    /**
     * Get total feedback count
     *
     * @param string|null $start_date
     * @return int
     */
    private function getTotalFeedback(?string $start_date = null): int
    {
        // This would query your feedback table
        // For now, return a stored option or mock data
        return get_option('surefeedback_total_feedback', 0);
    }
    
    /**
     * Get resolved feedback count
     *
     * @param string|null $start_date
     * @return int
     */
    private function getResolvedFeedback(?string $start_date = null): int
    {
        // This would query your feedback table with resolved status
        return get_option('surefeedback_resolved_feedback', 0);
    }
    
    /**
     * Get pending feedback count
     *
     * @param string|null $start_date
     * @return int
     */
    private function getPendingFeedbackCount(?string $start_date = null): int
    {
        // This would query your feedback table with pending status
        return get_option('surefeedback_pending_feedback', 0);
    }
    
    /**
     * Get total pages count
     *
     * @return int
     */
    private function getTotalPages(): int
    {
        return wp_count_posts('page')->publish;
    }
    
    /**
     * Get pages with feedback count
     *
     * @param string|null $start_date
     * @return int
     */
    private function getPagesWithFeedback(?string $start_date = null): int
    {
        // This would query to count unique pages with feedback
        return get_option('surefeedback_pages_with_feedback', 0);
    }
    
    /**
     * Get active users count
     *
     * @param string|null $start_date
     * @return int
     */
    private function getActiveUsers(?string $start_date = null): int
    {
        // This would query to count users who provided feedback
        return get_option('surefeedback_active_users', 0);
    }
    
    /**
     * Get total comments count
     *
     * @param string|null $start_date
     * @return int
     */
    private function getTotalComments(?string $start_date = null): int
    {
        // This would query your comments/feedback table
        return get_option('surefeedback_total_comments', 0);
    }
    
    /**
     * Get page feedback count
     *
     * @param int $page_id
     * @return int
     */
    private function getPageFeedbackCount(int $page_id): int
    {
        // This would query feedback count for specific page
        return get_post_meta($page_id, '_surefeedback_count', true) ?: 0;
    }
    
    /**
     * Calculate connection health score
     *
     * @return int
     */
    private function calculateConnectionHealth(): int
    {
        $score = 0;
        
        // Connected status (40 points)
        if (get_option('surefeedback_connected', false)) {
            $score += 40;
        }
        
        // Valid parent URL (20 points)
        if (!empty(get_option('surefeedback_parent_url', ''))) {
            $score += 20;
        }
        
        // Recent activity (20 points)
        $last_check = get_option('surefeedback_last_connection_check', 0);
        if ($last_check && (time() - $last_check) < 300) {
            $score += 20;
        }
        
        // Valid token (20 points)
        if (!empty(get_option('surefeedback_access_token', ''))) {
            $score += 20;
        }
        
        return $score;
    }
    
    /**
     * Calculate trends data
     *
     * @param int $days
     * @return array
     */
    private function calculateTrends(int $days): array
    {
        // This would calculate trend data over time
        // For now, return mock trend data
        return [
            'feedback_trend' => 'up',
            'feedback_change' => 15.2,
            'resolution_trend' => 'up',
            'resolution_change' => 8.7,
            'engagement_trend' => 'stable',
            'engagement_change' => 2.1
        ];
    }
    
    /**
     * Get last activity timestamp
     *
     * @return string|null
     */
    private function getLastActivity(): ?string
    {
        // This would get the most recent activity timestamp
        return get_option('surefeedback_last_activity', current_time('mysql'));
    }
}