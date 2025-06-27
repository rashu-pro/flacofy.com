<?php
/**
 * WordPress Rate Limiter Implementation
 * Protects registration, login, and OTP verification forms
 */

class WP_Rate_Limiter {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rate_limits';

        // Create table on activation
        $this->create_rate_limit_table();

        // Schedule cleanup job
        if (!wp_next_scheduled('rate_limit_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'rate_limit_cleanup');
        }

        add_action('rate_limit_cleanup', array($this, 'cleanup_old_records'));
    }

    /**
     * Create rate limit table
     */
    private function create_rate_limit_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            attempt_count INT DEFAULT 1,
            first_attempt DATETIME NOT NULL,
            last_attempt DATETIME NOT NULL,
            blocked_until DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_action (ip_address, action_type),
            INDEX idx_blocked_until (blocked_until),
            INDEX idx_last_attempt (last_attempt)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if action is rate limited
     */
    public function is_rate_limited($action_type, $max_attempts = 5, $time_window = 900) { // 15 minutes default
        global $wpdb;

        $ip = $this->get_client_ip();
        $current_time = current_time('mysql');
        $window_start = date('Y-m-d H:i:s', strtotime($current_time) - $time_window);

        // Check if IP is currently blocked
        $blocked_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE ip_address = %s AND action_type = %s AND blocked_until > %s",
            $ip, $action_type, $current_time
        ));

        if ($blocked_record) {
            return array(
                'blocked' => true,
                'message' => 'Too many attempts. Please try again later.',
                'blocked_until' => $blocked_record->blocked_until,
                'remaining_time' => strtotime($blocked_record->blocked_until) - time()
            );
        }

        // Count attempts in time window
        $attempt_count = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(attempt_count) FROM {$this->table_name}
             WHERE ip_address = %s AND action_type = %s AND last_attempt >= %s",
            $ip, $action_type, $window_start
        ));

        if ($attempt_count >= $max_attempts) {
            // Block the IP
            $this->block_ip($ip, $action_type, $time_window);

            return array(
                'blocked' => true,
                'message' => 'Too many attempts. You have been temporarily blocked.',
                'attempts' => $attempt_count
            );
        }

        return array(
            'blocked' => false,
            'attempts' => (int)$attempt_count,
            'remaining' => $max_attempts - (int)$attempt_count
        );
    }

    /**
     * Log an attempt
     */
    public function log_attempt($action_type) {
        global $wpdb;

        $ip = $this->get_client_ip();
        $current_time = current_time('mysql');

        // Check if there's an existing record in the last hour
        $existing_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE ip_address = %s AND action_type = %s AND last_attempt >= %s",
            $ip, $action_type, date('Y-m-d H:i:s', strtotime($current_time) - 3600)
        ));

        if ($existing_record) {
            // Update existing record
            $wpdb->update(
                $this->table_name,
                array(
                    'attempt_count' => $existing_record->attempt_count + 1,
                    'last_attempt' => $current_time
                ),
                array('id' => $existing_record->id),
                array('%d', '%s'),
                array('%d')
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $this->table_name,
                array(
                    'ip_address' => $ip,
                    'action_type' => $action_type,
                    'attempt_count' => 1,
                    'first_attempt' => $current_time,
                    'last_attempt' => $current_time
                ),
                array('%s', '%s', '%d', '%s', '%s')
            );
        }
    }

    /**
     * Block IP address
     */
    private function block_ip($ip, $action_type, $block_duration = 900) {
        global $wpdb;

        $current_time = current_time('mysql');
        $blocked_until = date('Y-m-d H:i:s', strtotime($current_time) + $block_duration);

        $wpdb->replace(
            $this->table_name,
            array(
                'ip_address' => $ip,
                'action_type' => $action_type,
                'attempt_count' => 1,
                'first_attempt' => $current_time,
                'last_attempt' => $current_time,
                'blocked_until' => $blocked_until
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Reset attempts for successful action
     */
    public function reset_attempts($action_type) {
        global $wpdb;

        $ip = $this->get_client_ip();

        $wpdb->delete(
            $this->table_name,
            array(
                'ip_address' => $ip,
                'action_type' => $action_type
            ),
            array('%s', '%s')
        );
    }

    /**
     * Cleanup old records
     */
    public function cleanup_old_records() {
        global $wpdb;

        $cleanup_time = date('Y-m-d H:i:s', strtotime('-24 hours'));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name}
             WHERE last_attempt < %s AND (blocked_until IS NULL OR blocked_until < %s)",
            $cleanup_time, current_time('mysql')
        ));
    }
}

// Initialize rate limiter
$wp_rate_limiter = new WP_Rate_Limiter();
