<?php

namespace SureFeedback\Repositories;

/**
 * Connection Repository
 *
 * Handles all connection-related data operations.
 *
 * @package SureFeedback\App\Repositories
 */
class ConnectionRepository extends BaseRepository
{
    /**
     * Get connection status
     *
     * @return array
     */
    public function getConnectionStatus(): array
    {
        return [
            'connected' => $this->isConnected(),
            'parent_url' => $this->getParentUrl(),
            'access_token' => $this->getAccessToken(),
            'signature' => $this->getSignature(),
            'last_check' => $this->getLastCheck(),
            'connection_time' => $this->getConnectionTime(),
            'user_id' => $this->getConnectedUserId(),
            'user_email' => $this->getConnectedUserEmail(),
        ];
    }

    /**
     * Check if site is connected
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return (bool) $this->getOption('connected', false);
    }

    /**
     * Set connection status
     *
     * @param bool $connected
     * @return bool
     */
    public function setConnected(bool $connected): bool
    {
        return $this->setOption('connected', $connected);
    }

    /**
     * Get parent URL
     *
     * @return string|null
     */
    public function getParentUrl(): ?string
    {
        return $this->getOption('parent_url');
    }

    /**
     * Set parent URL
     *
     * @param string $url
     * @return bool
     */
    public function setParentUrl(string $url): bool
    {
        $sanitizedUrl = esc_url_raw($url);
        return $this->setOption('parent_url', $sanitizedUrl);
    }

    /**
     * Get access token
     *
     * @return string|null
     */
    public function getAccessToken(): ?string
    {
        return $this->getOption('access_token');
    }

    /**
     * Set access token
     *
     * @param string $token
     * @return bool
     */
    public function setAccessToken(string $token): bool
    {
        return $this->setOption('access_token', sanitize_text_field($token));
    }

    /**
     * Get signature
     *
     * @return string|null
     */
    public function getSignature(): ?string
    {
        return $this->getOption('signature');
    }

    /**
     * Set signature
     *
     * @param string $signature
     * @return bool
     */
    public function setSignature(string $signature): bool
    {
        return $this->setOption('signature', sanitize_text_field($signature));
    }

    /**
     * Get last check timestamp
     *
     * @return string|null
     */
    public function getLastCheck(): ?string
    {
        return $this->getOption('last_check');
    }

    /**
     * Set last check timestamp
     *
     * @param string|null $timestamp
     * @return bool
     */
    public function setLastCheck(?string $timestamp = null): bool
    {
        $timestamp = $timestamp ?: current_time('mysql');
        return $this->setOption('last_check', $timestamp);
    }

    /**
     * Get connection time
     *
     * @return string|null
     */
    public function getConnectionTime(): ?string
    {
        return $this->getOption('connection_time');
    }

    /**
     * Set connection time
     *
     * @param string|null $timestamp
     * @return bool
     */
    public function setConnectionTime(?string $timestamp = null): bool
    {
        $timestamp = $timestamp ?: current_time('mysql');
        return $this->setOption('connection_time', $timestamp);
    }

    /**
     * Get connected user ID
     *
     * @return int|null
     */
    public function getConnectedUserId(): ?int
    {
        $userId = $this->getOption('user_id');
        return $userId ? (int) $userId : null;
    }

    /**
     * Set connected user ID
     *
     * @param int $userId
     * @return bool
     */
    public function setConnectedUserId(int $userId): bool
    {
        return $this->setOption('user_id', $userId);
    }

    /**
     * Get connected user email
     *
     * @return string|null
     */
    public function getConnectedUserEmail(): ?string
    {
        return $this->getOption('user_email');
    }

    /**
     * Set connected user email
     *
     * @param string $email
     * @return bool
     */
    public function setConnectedUserEmail(string $email): bool
    {
        $sanitizedEmail = sanitize_email($email);
        return $this->setOption('user_email', $sanitizedEmail);
    }

    /**
     * Store connection data
     *
     * @param array $data
     * @return bool
     */
    public function storeConnectionData(array $data): bool
    {
        $sanitizedData = $this->sanitizeData($data);
        
        $success = true;
        
        if (isset($sanitizedData['parent_url'])) {
            $success = $success && $this->setParentUrl($sanitizedData['parent_url']);
        }
        
        if (isset($sanitizedData['access_token'])) {
            $success = $success && $this->setAccessToken($sanitizedData['access_token']);
        }
        
        if (isset($sanitizedData['signature'])) {
            $success = $success && $this->setSignature($sanitizedData['signature']);
        }
        
        if (isset($sanitizedData['user_id'])) {
            $success = $success && $this->setConnectedUserId((int) $sanitizedData['user_id']);
        }
        
        if (isset($sanitizedData['user_email'])) {
            $success = $success && $this->setConnectedUserEmail($sanitizedData['user_email']);
        }

        // Set connection status and timestamp
        $success = $success && $this->setConnected(true);
        $success = $success && $this->setConnectionTime();
        $success = $success && $this->setLastCheck();

        return $success;
    }

    /**
     * Clear connection data
     *
     * @return bool
     */
    public function clearConnectionData(): bool
    {
        $keys = [
            'connected',
            'parent_url',
            'access_token',
            'signature',
            'user_id',
            'user_email',
            'connection_time',
            'last_check',
        ];

        $success = true;
        foreach ($keys as $key) {
            $success = $success && $this->deleteOption($key);
        }

        return $success;
    }

    /**
     * Get connection health data
     *
     * @return array
     */
    public function getConnectionHealth(): array
    {
        $lastCheck = $this->getLastCheck();
        $isHealthy = true;
        $issues = [];

        // Check if we have all required data
        if (!$this->getParentUrl()) {
            $isHealthy = false;
            $issues[] = 'Missing parent URL';
        }

        if (!$this->getAccessToken()) {
            $isHealthy = false;
            $issues[] = 'Missing access token';
        }

        if (!$this->getSignature()) {
            $isHealthy = false;
            $issues[] = 'Missing signature';
        }

        // Check if last check is recent (within last hour)
        if ($lastCheck) {
            $lastCheckTime = strtotime($lastCheck);
            $oneHourAgo = time() - 3600;
            
            if ($lastCheckTime < $oneHourAgo) {
                $isHealthy = false;
                $issues[] = 'Connection not verified recently';
            }
        } else {
            $isHealthy = false;
            $issues[] = 'No connection check recorded';
        }

        return [
            'healthy' => $isHealthy,
            'issues' => $issues,
            'last_check' => $lastCheck,
            'connection_age' => $this->getConnectionAge(),
        ];
    }

    /**
     * Get connection age in seconds
     *
     * @return int|null
     */
    public function getConnectionAge(): ?int
    {
        $connectionTime = $this->getConnectionTime();
        
        if (!$connectionTime) {
            return null;
        }

        return time() - strtotime($connectionTime);
    }

    /**
     * Update last check timestamp
     *
     * @return bool
     */
    public function updateLastCheck(): bool
    {
        return $this->setLastCheck();
    }
}