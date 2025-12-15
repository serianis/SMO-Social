<?php
/**
 * View Data Sanitizer Helper
 *
 * Provides safe access to array values with defaults to prevent undefined array key warnings.
 * Used to normalize API/DB payloads before passing to admin view templates.
 *
 * @package SMO_Social
 * @subpackage Admin/Helpers
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ViewDataSanitizer class
 *
 * Lightweight helper for safely accessing and formatting data in admin views
 */
class ViewDataSanitizer {

    /**
     * Safe get value from array with default
     *
     * @param array $array The array to get value from
     * @param string $key The key to get
     * @param mixed $default The default value if key doesn't exist
     * @return mixed The value or default
     */
    public static function safe_get($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }
        return isset($array[$key]) ? $array[$key] : $default;
    }

    /**
     * Safe get from list of arrays using array_column equivalent
     *
     * @param array $array_list Array of arrays
     * @param string $column_key The key to extract from each array
     * @param string $index_key Optional key to use as array key
     * @return array Extracted values
     */
    public static function safe_list($array_list, $column_key, $index_key = null) {
        if (!is_array($array_list) || empty($array_list)) {
            return array();
        }

        $result = array();
        foreach ($array_list as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!isset($item[$column_key])) {
                continue;
            }

            if ($index_key && isset($item[$index_key])) {
                $result[$item[$index_key]] = $item[$column_key];
            } else {
                $result[] = $item[$column_key];
            }
        }

        return $result;
    }

    /**
     * Safe filter array based on a key's value
     *
     * @param array $array_list Array of arrays to filter
     * @param string $key The key to check
     * @param mixed $value The value to match
     * @return array Filtered array
     */
    public static function safe_filter($array_list, $key, $value) {
        if (!is_array($array_list) || empty($array_list)) {
            return array();
        }

        return array_filter($array_list, function ($item) use ($key, $value) {
            if (!is_array($item)) {
                return false;
            }
            return isset($item[$key]) && $item[$key] === $value;
        });
    }

    /**
     * Safe filter array - generic callback version
     *
     * @param array $array_list Array of arrays to filter
     * @param callable $callback Callback to determine if item should be included
     * @return array Filtered array
     */
    public static function safe_filter_callback($array_list, $callback) {
        if (!is_array($array_list) || empty($array_list)) {
            return array();
        }

        return array_filter($array_list, $callback);
    }

    /**
     * Count items matching a key-value pair
     *
     * @param array $array_list Array of arrays
     * @param string $key The key to check
     * @param mixed $value The value to match
     * @return int Count of matching items
     */
    public static function safe_count($array_list, $key, $value) {
        return count(self::safe_filter($array_list, $key, $value));
    }

    /**
     * Format a timestamp for display
     *
     * @param string|int $timestamp The timestamp to format
     * @param string $format The date format (default: 'Y-m-d H:i')
     * @return string Formatted date or placeholder text
     */
    public static function format_timestamp($timestamp, $format = 'Y-m-d H:i') {
        if (empty($timestamp)) {
            return __('Never', 'smo-social');
        }

        try {
            $time = is_numeric($timestamp) ? intval($timestamp) : strtotime($timestamp);
            if ($time === false) {
                return '-';
            }
            return date($format, $time);
        } catch (\Exception $e) {
            return '-';
        }
    }

    /**
     * Format a date for display
     *
     * @param string $date The date to format
     * @param string $format The date format (default: 'Y-m-d')
     * @return string Formatted date or placeholder text
     */
    public static function format_date($date, $format = 'Y-m-d') {
        if (empty($date)) {
            return '-';
        }

        try {
            $time = strtotime($date);
            if ($time === false) {
                return '-';
            }
            return date($format, $time);
        } catch (\Exception $e) {
            return '-';
        }
    }

    /**
     * Get a status badge CSS class
     *
     * @param string $status The status value
     * @return string CSS class name
     */
    public static function get_status_badge_class($status) {
        $status = strtolower($status);
        $class_map = array(
            'published'  => 'smo-status-published',
            'scheduled'  => 'smo-status-scheduled',
            'draft'      => 'smo-status-draft',
            'active'     => 'smo-status-published',
            'inactive'   => 'smo-status-draft',
            'failed'     => 'smo-status-failed',
            'pending'    => 'smo-status-pending',
        );

        return isset($class_map[$status]) ? $class_map[$status] : 'smo-status-default';
    }

    /**
     * Get a status display label
     *
     * @param string $status The status value
     * @return string Display label
     */
    public static function get_status_label($status) {
        $status = strtolower($status);
        $labels = array(
            'published' => __('Published', 'smo-social'),
            'scheduled' => __('Scheduled', 'smo-social'),
            'draft'     => __('Draft', 'smo-social'),
            'active'    => __('Active', 'smo-social'),
            'inactive'  => __('Inactive', 'smo-social'),
            'failed'    => __('Failed', 'smo-social'),
            'pending'   => __('Pending', 'smo-social'),
            'archived'  => __('Archived', 'smo-social'),
        );

        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }

    /**
     * Normalize API key data with defaults
     *
     * @param array $key API key row from database
     * @return array Normalized key data
     */
    public static function normalize_api_key($key) {
        if (!is_array($key)) {
            $key = array();
        }

        return array(
            'id'          => self::safe_get($key, 'id', ''),
            'name'        => self::safe_get($key, 'name', __('Untitled Key', 'smo-social')),
            'key'         => self::safe_get($key, 'key', ''),
            'status'      => self::safe_get($key, 'status', 'inactive'),
            'permissions' => self::ensure_array(self::safe_get($key, 'permissions', array())),
            'created'     => self::format_date(self::safe_get($key, 'created_at', ''), 'Y-m-d'),
            'created_at'  => self::safe_get($key, 'created_at', ''),
            'last_used'   => self::format_timestamp(self::safe_get($key, 'last_used_at', ''), 'Y-m-d H:i'),
            'last_used_at' => self::safe_get($key, 'last_used_at', ''),
        );
    }

    /**
     * Normalize post data with defaults
     *
     * @param array $post Post row from database
     * @return array Normalized post data
     */
    public static function normalize_post($post) {
        if (!is_array($post)) {
            $post = array();
        }

        return array(
            'id'               => self::safe_get($post, 'id', ''),
            'title'            => self::safe_get($post, 'title', __('(No title)', 'smo-social')),
            'content'          => self::safe_get($post, 'content', ''),
            'platform_slug'    => self::safe_get($post, 'platform_slug', ''),
            'status'           => self::safe_get($post, 'status', 'draft'),
            'created_at'       => self::safe_get($post, 'created_at', ''),
            'created'          => self::format_date(self::safe_get($post, 'created_at', ''), 'Y-m-d'),
            'scheduled_time'   => self::safe_get($post, 'scheduled_time', ''),
            'scheduled'        => self::format_timestamp(self::safe_get($post, 'scheduled_time', ''), 'Y-m-d H:i'),
            'user_id'          => self::safe_get($post, 'user_id', 0),
            'media_attachments' => self::safe_get($post, 'media_attachments', ''),
        );
    }

    /**
     * Normalize calendar event data
     *
     * @param array $event Event row from database
     * @return array Normalized event data
     */
    public static function normalize_calendar_event($event) {
        if (!is_array($event)) {
            $event = array();
        }

        return array(
            'id'              => self::safe_get($event, 'id', ''),
            'title'           => self::safe_get($event, 'title', __('Untitled Event', 'smo-social')),
            'content'         => self::safe_get($event, 'content', ''),
            'status'          => self::safe_get($event, 'status', 'draft'),
            'scheduled_time'  => self::safe_get($event, 'scheduled_time', ''),
            'date'            => self::format_date(self::safe_get($event, 'scheduled_time', ''), 'Y-m-d'),
            'time'            => self::format_timestamp(self::safe_get($event, 'scheduled_time', ''), 'H:i'),
        );
    }

    /**
     * Ensure value is an array
     *
     * @param mixed $value The value to ensure is an array
     * @return array The array or empty array
     */
    public static function ensure_array($value) {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && !empty($value)) {
            // Try to decode JSON
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            // Return as single-item array
            return array($value);
        }

        return array();
    }

    /**
     * Sum values from array using safe_list
     *
     * @param array $array_list Array of arrays
     * @param string $key The key to sum
     * @return int|float Total sum
     */
    public static function safe_sum($array_list, $key) {
        if (!is_array($array_list) || empty($array_list)) {
            return 0;
        }

        $sum = 0;
        foreach ($array_list as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item[$key])) {
                $value = $item[$key];
                if (is_numeric($value)) {
                    $sum += floatval($value);
                }
            }
        }

        return $sum;
    }

    /**
     * Calculate average of values
     *
     * @param array $array_list Array of arrays
     * @param string $key The key to average
     * @return float|int Average or 0
     */
    public static function safe_average($array_list, $key) {
        if (!is_array($array_list) || empty($array_list)) {
            return 0;
        }

        $sum = self::safe_sum($array_list, $key);
        $count = count($array_list);

        return $count > 0 ? $sum / $count : 0;
    }
}
