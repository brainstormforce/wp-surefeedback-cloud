<?php

namespace SureFeedback\App\Repositories;

/**
 * Dashboard Repository
 *
 * Handles all dashboard-related data operations and statistics.
 *
 * @package SureFeedback\App\Repositories
 */
class DashboardRepository extends BaseRepository
{
    /**
     * Get dashboard statistics
     *
     * @param string $period
     * @return array
     */
    public function getStats(string $period = 'all'): array
    {
        $stats = $this->getTransient('dashboard_stats_' . $period);
        
        if ($stats === null) {
            $stats = $this->calculateStats($period);
            $this->setTransient('dashboard_stats_' . $period, $stats, 300); // Cache for 5 minutes
        }

        return $stats;
    }

    /**
     * Calculate dashboard statistics
     *
     * @param string $period
     * @return array
     */
    protected function calculateStats(string $period): array
    {
        // For now, return mock data. In a real implementation,
        // this would query the actual data sources.
        $baseStats = [
            'total_comments' => $this->getOption('total_comments', 0),
            'active_projects' => $this->getOption('active_projects', 0),
            'pending_reviews' => $this->getOption('pending_reviews', 0),
            'resolved_comments' => $this->getOption('resolved_comments', 0),
        ];

        // Adjust stats based on period
        switch ($period) {
            case 'day':
                return array_merge($baseStats, [
                    'period' => 'Today',
                    'comments_today' => $this->getCommentsCount('today'),
                ]);
            
            case 'week':
                return array_merge($baseStats, [
                    'period' => 'This Week',
                    'comments_this_week' => $this->getCommentsCount('week'),
                ]);
            
            case 'month':
                return array_merge($baseStats, [
                    'period' => 'This Month',
                    'comments_this_month' => $this->getCommentsCount('month'),
                ]);
            
            case 'year':
                return array_merge($baseStats, [
                    'period' => 'This Year',
                    'comments_this_year' => $this->getCommentsCount('year'),
                ]);
            
            default:
                return array_merge($baseStats, [
                    'period' => 'All Time',
                    'last_updated' => current_time('mysql'),
                ]);
        }
    }

    /**
     * Get comments count for a specific period
     *
     * @param string $period
     * @return int
     */
    protected function getCommentsCount(string $period): int
    {
        // Mock implementation - would query actual comment data
        $counts = [
            'today' => 5,
            'week' => 23,
            'month' => 87,
            'year' => 432,
        ];

        return $counts[$period] ?? 0;
    }

    /**
     * Get recent activity
     *
     * @param int $limit
     * @param int $offset
     * @param string $type
     * @return array
     */
    public function getRecentActivity(int $limit = 10, int $offset = 0, string $type = 'all'): array
    {
        $cacheKey = "recent_activity_{$type}_{$limit}_{$offset}";
        $activity = $this->getTransient($cacheKey);
        
        if ($activity === null) {
            $activity = $this->fetchRecentActivity($limit, $offset, $type);
            $this->setTransient($cacheKey, $activity, 300); // Cache for 5 minutes
        }

        return $activity;
    }

    /**
     * Fetch recent activity from data sources
     *
     * @param int $limit
     * @param int $offset
     * @param string $type
     * @return array
     */
    protected function fetchRecentActivity(int $limit, int $offset, string $type): array
    {
        // Mock implementation - would fetch from actual activity log
        $activities = [];
        
        for ($i = $offset; $i < $offset + $limit; $i++) {
            $activities[] = [
                'id' => $i + 1,
                'type' => $this->getRandomActivityType($type),
                'message' => $this->getActivityMessage($i),
                'timestamp' => date('Y-m-d H:i:s', time() - ($i * 3600)),
                'user' => $this->getRandomUser(),
            ];
        }

        return [
            'items' => $activities,
            'total' => 50, // Mock total
            'has_more' => ($offset + $limit) < 50,
        ];
    }

    /**
     * Get random activity type
     *
     * @param string $filter
     * @return string
     */
    protected function getRandomActivityType(string $filter): string
    {
        $types = ['comment', 'connection', 'setting'];
        
        if ($filter !== 'all' && in_array($filter, $types)) {
            return $filter;
        }

        return $types[array_rand($types)];
    }

    /**
     * Get activity message
     *
     * @param int $index
     * @return string
     */
    protected function getActivityMessage(int $index): string
    {
        $messages = [
            'New comment added to project',
            'Connection status updated',
            'Settings configuration changed',
            'User permissions modified',
            'Webhook notification sent',
            'Email notification delivered',
            'Project status updated',
            'Comment resolved',
        ];

        return $messages[$index % count($messages)];
    }

    /**
     * Get random user for activity
     *
     * @return array
     */
    protected function getRandomUser(): array
    {
        $users = [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['id' => 3, 'name' => 'Admin User', 'email' => 'admin@example.com'],
        ];

        return $users[array_rand($users)];
    }

    /**
     * Get quick access data
     *
     * @return array
     */
    public function getQuickAccessData(): array
    {
        return [
            'recent_projects' => $this->getRecentProjects(),
            'pending_approvals' => $this->getPendingApprovals(),
            'quick_links' => $this->getQuickLinks(),
            'notifications' => $this->getNotifications(),
        ];
    }

    /**
     * Get recent projects
     *
     * @return array
     */
    protected function getRecentProjects(): array
    {
        // Mock implementation
        return [
            ['id' => 1, 'name' => 'Website Redesign', 'status' => 'active'],
            ['id' => 2, 'name' => 'Mobile App', 'status' => 'pending'],
            ['id' => 3, 'name' => 'Brand Guidelines', 'status' => 'completed'],
        ];
    }

    /**
     * Get pending approvals
     *
     * @return array
     */
    protected function getPendingApprovals(): array
    {
        // Mock implementation
        return [
            ['id' => 1, 'comment' => 'Header needs adjustment', 'project' => 'Website Redesign'],
            ['id' => 2, 'comment' => 'Color scheme feedback', 'project' => 'Brand Guidelines'],
        ];
    }

    /**
     * Get quick links
     *
     * @return array
     */
    protected function getQuickLinks(): array
    {
        return [
            ['name' => 'Settings', 'url' => admin_url('admin.php?page=surefeedback-settings')],
            ['name' => 'Help & Support', 'url' => 'https://surefeedback.com/support'],
            ['name' => 'Documentation', 'url' => 'https://surefeedback.com/docs'],
        ];
    }

    /**
     * Get notifications
     *
     * @return array
     */
    protected function getNotifications(): array
    {
        return [
            ['id' => 1, 'message' => 'Connection verified successfully', 'type' => 'success'],
            ['id' => 2, 'message' => '3 new comments awaiting review', 'type' => 'info'],
        ];
    }

    /**
     * Update dashboard stats
     *
     * @param array $stats
     * @return bool
     */
    public function updateStats(array $stats): bool
    {
        $success = true;
        
        foreach ($stats as $key => $value) {
            $success = $success && $this->setOption($key, (int) $value);
        }

        // Clear cached stats
        $this->clearStatsCache();

        return $success;
    }

    /**
     * Clear stats cache
     *
     * @return void
     */
    public function clearStatsCache(): void
    {
        $periods = ['all', 'day', 'week', 'month', 'year'];
        
        foreach ($periods as $period) {
            $this->deleteTransient('dashboard_stats_' . $period);
        }
    }

    /**
     * Increment a stat counter
     *
     * @param string $stat
     * @param int $increment
     * @return bool
     */
    public function incrementStat(string $stat, int $increment = 1): bool
    {
        $current = (int) $this->getOption($stat, 0);
        $new = $current + $increment;
        
        $result = $this->setOption($stat, $new);
        
        if ($result) {
            $this->clearStatsCache();
        }

        return $result;
    }

    /**
     * Get widget data
     *
     * @param string $widget
     * @return array
     */
    public function getWidgetData(string $widget): array
    {
        switch ($widget) {
            case 'stats':
                return $this->getStats();
            
            case 'activity':
                return $this->getRecentActivity(5);
            
            case 'quick_access':
                return $this->getQuickAccessData();
            
            default:
                return [];
        }
    }

    /**
     * Record activity
     *
     * @param string $type
     * @param string $message
     * @param array $metadata
     * @return bool
     */
    public function recordActivity(string $type, string $message, array $metadata = []): bool
    {
        $activity = [
            'type' => sanitize_text_field($type),
            'message' => sanitize_text_field($message),
            'metadata' => $this->sanitizeData($metadata),
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
        ];

        // In a real implementation, this would store in a custom table or log
        // For now, we'll just clear the activity cache to force refresh
        $this->deleteTransient('recent_activity_all_10_0');
        
        return true;
    }
}