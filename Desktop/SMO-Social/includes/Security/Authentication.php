<?php
namespace SMO_Social\Security;

class Authentication {
    private $failed_attempts = array();
    private $max_attempts = 5;
    private $lockout_time = 900; // 15 minutes

    public function validate_login($email, $password) {
        // Basic validation - in real implementation would check against database
        if (empty($email) || empty($password)) {
            return false;
        }

        // Check if account is locked
        if ($this->is_account_locked($email)) {
            return false;
        }

        // Simulate login check
        $valid_login = ($email === 'test@example.com' && $password === 'password123');

        if (!$valid_login) {
            $this->track_failed_attempt($email);
            return false;
        }

        // Reset failed attempts on successful login
        unset($this->failed_attempts[$email]);
        return true;
    }

    public function check_password_strength($password) {
        if (strlen($password) < 8) {
            return false;
        }

        // Check for uppercase, lowercase, numbers, special chars
        $has_uppercase = preg_match('/[A-Z]/', $password);
        $has_lowercase = preg_match('/[a-z]/', $password);
        $has_numbers = preg_match('/[0-9]/', $password);
        $has_special = preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password);

        return $has_uppercase && $has_lowercase && $has_numbers && $has_special;
    }

    public function create_session($user_id) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        return session_id();
    }

    public function destroy_session() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return true;
    }

    public function track_failed_attempt($email) {
        if (!isset($this->failed_attempts[$email])) {
            $this->failed_attempts[$email] = array();
        }

        $this->failed_attempts[$email][] = time();
        return true;
    }

    public function is_account_locked($email) {
        if (!isset($this->failed_attempts[$email])) {
            return false;
        }

        $recent_attempts = array_filter($this->failed_attempts[$email], function($time) {
            return (time() - $time) < $this->lockout_time;
        });

        $this->failed_attempts[$email] = $recent_attempts;

        return count($recent_attempts) >= $this->max_attempts;
    }
}
