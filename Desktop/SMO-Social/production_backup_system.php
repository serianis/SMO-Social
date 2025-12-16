<?php
/**
 * SMO Social Production Backup & Disaster Recovery System
 * 
 * This script provides automated backup functionality including database,
 * files, and complete system backup with compression and remote storage.
 * 
 * @package SMO_Social
 * @version 1.0.0
 * @author SMO Social Production Team
 */

defined('ABSPATH') || exit;

// Production Backup Constants
define('SMO_BACKUP_DIR', WP_CONTENT_DIR . '/smo-backups/');
define('SMO_BACKUP_RETENTION_DAYS', 30);
define('SMO_MAX_BACKUP_SIZE', '2GB');
define('SMO_REMOTE_BACKUP_ENABLED', false);

/**
 * Backup & Disaster Recovery Manager
 */
class SMO_Backup_Manager {
    
    private $backup_dir;
    private $log_file;
    private $backup_info;
    private $remote_storage_config;
    
    public function __construct() {
        $this->backup_dir = SMO_BACKUP_DIR;
        $this->log_file = WP_CONTENT_DIR . '/smo-backup.log';
        $this->backup_info = [];
        $this->remote_storage_config = [];
        
        // Create backup directory if it doesn't exist
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }
    }
    
    /**
     * Run complete backup operation
     */
    public function run_backup($options = []) {
        echo "💾 SMO Social Production Backup System\n";
        echo "======================================\n\n";
        
        $defaults = [
            'database' => true,
            'files' => true,
            'compress' => true,
            'remote_upload' => false,
            'full_system' => false
        ];
        $options = array_merge($defaults, $options);
        
        $backup_id = date('Y-m-d_H-i-s');
        $backup_start = microtime(true);
        
        echo "🔄 Starting backup process (ID: {$backup_id})\n\n";
        
        try {
            // Initialize backup info
            $this->initialize_backup_info($backup_id, $options);
            
            // Database backup
            if ($options['database']) {
                $this->backup_database($options);
            }
            
            // Files backup
            if ($options['files']) {
                $this->backup_files($options);
            }
            
            // Full system backup (includes WordPress core)
            if ($options['full_system']) {
                $this->backup_full_system($options);
            }
            
            // Create backup manifest
            $this->create_backup_manifest();
            
            // Compress if requested
            if ($options['compress']) {
                $this->compress_backup();
            }
            
            // Upload to remote storage if enabled
            if ($options['remote_upload']) {
                $this->upload_to_remote_storage();
            }
            
            // Clean up old backups
            $this->cleanup_old_backups();
            
            // Generate backup report
            $this->generate_backup_report();
            
            $backup_time = microtime(true) - $backup_start;
            echo "\n✅ Backup completed successfully in " . round($backup_time, 2) . " seconds\n";
            echo "📁 Backup location: {$this->backup_dir}{$backup_id}\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "\n❌ Backup failed: " . $e->getMessage() . "\n";
            $this->log_error("Backup failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize backup information
     */
    private function initialize_backup_info($backup_id, $options) {
        global $wpdb;
        
        $this->backup_info = [
            'backup_id' => $backup_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'site_url' => site_url(),
            'options' => $options,
            'files' => [],
            'database' => [],
            'system_info' => [
                'disk_free_space' => disk_free_space('.'),
                'disk_total_space' => disk_total_space('.'),
                'memory_usage' => memory_get_usage(true),
                'max_execution_time' => ini_get('max_execution_time')
            ]
        ];
    }
    
    /**
     * Backup database
     */
    private function backup_database($options) {
        echo "🗄️  Backing up database...\n";
        
        global $wpdb;
        
        $database_file = $this->backup_dir . $this->backup_info['backup_id'] . '/database.sql';
        
        // Ensure backup subdirectory exists
        wp_mkdir_p(dirname($database_file));
        
        // Get all tables
        $tables = $wpdb->get_col('SHOW TABLES');
        
        $sql_content = "-- SMO Social Database Backup\n";
        $sql_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql_content .= "-- WordPress Version: " . get_bloginfo('version') . "\n\n";
        
        $total_rows = 0;
        
        foreach ($tables as $table) {
            echo "   📋 Backing up table: {$table}\n";
            
            // Get table structure
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            $sql_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql_content .= $create_table[1] . ";\n\n";
            
            // Get table data
            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            $table_rows = count($rows);
            
            if ($table_rows > 0) {
                $sql_content .= "INSERT INTO `{$table}` VALUES\n";
                
                foreach ($rows as $index => $row) {
                    $values = [];
                    foreach ($row as $value) {
                        $values[] = "'" . $wpdb->esc($value) . "'";
                    }
                    $sql_content .= "(" . implode(', ', $values) . ")";
                    $sql_content .= $index < ($table_rows - 1) ? ",\n" : ";\n";
                }
                
                $sql_content .= "\n";
            }
            
            $total_rows += $table_rows;
        }
        
        // Write to file
        file_put_contents($database_file, $sql_content);
        
        $this->backup_info['database'] = [
            'file' => $database_file,
            'tables' => count($tables),
            'total_rows' => $total_rows,
            'file_size' => filesize($database_file)
        ];
        
        echo "   ✅ Database backed up ({$total_rows} rows across " . count($tables) . " tables)\n";
    }
    
    /**
     * Backup WordPress files
     */
    private function backup_files($options) {
        echo "📁 Backing up WordPress files...\n";
        
        $files_to_backup = [
            'wp-content' => WP_CONTENT_DIR,
            'wp-config.php' => ABSPATH . 'wp-config.php',
            '.htaccess' => ABSPATH . '.htaccess'
        ];
        
        $files_backup_dir = $this->backup_dir . $this->backup_info['backup_id'] . '/files/';
        wp_mkdir_p($files_backup_dir);
        
        $total_files = 0;
        $total_size = 0;
        
        foreach ($files_to_backup as $name => $path) {
            if (!file_exists($path)) {
                echo "   ⚠️  Skipping missing file/directory: {$name}\n";
                continue;
            }
            
            echo "   📁 Backing up: {$name}\n";
            
            $destination = $files_backup_dir . $name;
            
            if (is_dir($path)) {
                // Copy directory
                $this->copy_directory($path, $destination);
                $files_info = $this->get_directory_info($path);
                $total_files += $files_info['file_count'];
                $total_size += $files_info['total_size'];
            } else {
                // Copy file
                wp_mkdir_p(dirname($destination));
                copy($path, $destination);
                $total_files++;
                $total_size += filesize($path);
            }
        }
        
        // Backup custom files
        $this->backup_custom_files($files_backup_dir);
        
        $this->backup_info['files'] = [
            'directory' => $files_backup_dir,
            'total_files' => $total_files,
            'total_size' => $total_size
        ];
        
        echo "   ✅ Files backed up ({$total_files} files, " . $this->format_bytes($total_size) . ")\n";
    }
    
    /**
     * Backup full system (WordPress core + everything)
     */
    private function backup_full_system($options) {
        echo "🔄 Backing up complete WordPress installation...\n";
        
        $system_backup_dir = $this->backup_dir . $this->backup_info['backup_id'] . '/system/';
        wp_mkdir_p($system_backup_dir);
        
        $exclude_patterns = [
            'wp-content/cache',
            'wp-content/upgrade',
            'wp-content/uploads/cache',
            '.git',
            'node_modules',
            '*.log',
            'wp-config.php',
            '.htaccess'
        ];
        
        $this->copy_directory_excluding(
            ABSPATH,
            $system_backup_dir . 'wordpress/',
            $exclude_patterns
        );
        
        echo "   ✅ Full WordPress installation backed up\n";
    }
    
    /**
     * Copy directory with exclusions
     */
    private function copy_directory_excluding($source, $destination, $exclude_patterns) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $file_path = $file->getRealPath();
            $relative_path = str_replace($source, '', $file_path);
            
            // Check if file should be excluded
            $exclude = false;
            foreach ($exclude_patterns as $pattern) {
                if (fnmatch($pattern, basename($file_path)) || 
                    strpos($file_path, $pattern) !== false) {
                    $exclude = true;
                    break;
                }
            }
            
            if ($exclude) continue;
            
            $dest_path = $destination . $relative_path;
            
            if ($file->isDir()) {
                wp_mkdir_p($dest_path);
            } else {
                wp_mkdir_p(dirname($dest_path));
                copy($file_path, $dest_path);
            }
        }
    }
    
    /**
     * Copy directory recursively
     */
    private function copy_directory($source, $destination) {
        if (!file_exists($destination)) {
            wp_mkdir_p($destination);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $file_path = $file->getRealPath();
            $relative_path = str_replace($source, '', $file_path);
            $dest_path = $destination . $relative_path;
            
            if ($file->isDir()) {
                wp_mkdir_p($dest_path);
            } else {
                wp_mkdir_p(dirname($dest_path));
                copy($file_path, $dest_path);
            }
        }
    }
    
    /**
     * Get directory information
     */
    private function get_directory_info($directory) {
        $file_count = 0;
        $total_size = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $file_count++;
                $total_size += $file->getSize();
            }
        }
        
        return [
            'file_count' => $file_count,
            'total_size' => $total_size
        ];
    }
    
    /**
     * Backup custom configuration files
     */
    private function backup_custom_files($destination) {
        $custom_files = [
            'production_config.php' => 'production_config.php',
            '.smo-social-config.php' => '.smo-social-config.php',
            'composer.json' => 'composer.json',
            'composer.lock' => 'composer.lock'
        ];
        
        foreach ($custom_files as $name => $file) {
            if (file_exists($file)) {
                copy($file, $destination . $name);
            }
        }
    }
    
    /**
     * Create backup manifest
     */
    private function create_backup_manifest() {
        $manifest_file = $this->backup_dir . $this->backup_info['backup_id'] . '/MANIFEST.json';
        
        $manifest = [
            'backup_info' => $this->backup_info,
            'checksums' => $this->generate_checksums(),
            'created_by' => 'SMO Social Backup System',
            'version' => '1.0.0'
        ];
        
        file_put_contents($manifest_file, json_encode($manifest, JSON_PRETTY_PRINT));
        
        echo "📋 Backup manifest created\n";
    }
    
    /**
     * Generate checksums for backup integrity
     */
    private function generate_checksums() {
        $backup_dir = $this->backup_dir . $this->backup_info['backup_id'];
        $checksums = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative_path = str_replace($backup_dir, '', $file->getRealPath());
                $checksums[$relative_path] = md5_file($file->getRealPath());
            }
        }
        
        return $checksums;
    }
    
    /**
     * Compress backup
     */
    private function compress_backup() {
        echo "🗜️  Compressing backup...\n";
        
        $backup_dir = $this->backup_dir . $this->backup_info['backup_id'];
        $compressed_file = $this->backup_dir . $this->backup_info['backup_id'] . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($compressed_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($backup_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                $file_path = $file->getRealPath();
                $relative_path = str_replace($backup_dir . '/', '', $file_path);
                $zip->addFile($file_path, $relative_path);
            }
            
            $zip->close();
            
            // Remove uncompressed directory
            $this->remove_directory($backup_dir);
            
            $this->backup_info['compressed_file'] = $compressed_file;
            $this->backup_info['compressed_size'] = filesize($compressed_file);
            
            echo "   ✅ Backup compressed to " . $this->format_bytes(filesize($compressed_file)) . "\n";
        } else {
            throw new Exception("Failed to create compressed archive");
        }
    }
    
    /**
     * Upload backup to remote storage
     */
    private function upload_to_remote_storage() {
        echo "☁️  Uploading to remote storage...\n";
        
        // This would integrate with cloud storage services like AWS S3, Google Drive, etc.
        // For now, we'll just log the attempt
        
        if (empty($this->remote_storage_config)) {
            echo "   ⚠️  Remote storage not configured\n";
            return;
        }
        
        echo "   ✅ Remote upload configured (implementation depends on cloud provider)\n";
    }
    
    /**
     * Clean up old backups
     */
    private function cleanup_old_backups() {
        echo "🧹 Cleaning up old backups...\n";
        
        $cutoff_time = time() - (SMO_BACKUP_RETENTION_DAYS * 24 * 3600);
        $deleted_count = 0;
        
        $files = glob($this->backup_dir . '*.zip');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
                $deleted_count++;
            }
        }
        
        echo "   ✅ Cleaned up {$deleted_count} old backup files\n";
    }
    
    /**
     * Generate backup report
     */
    private function generate_backup_report() {
        echo "📊 Generating backup report...\n";
        
        $report = [
            'backup_id' => $this->backup_info['backup_id'],
            'timestamp' => $this->backup_info['timestamp'],
            'database' => $this->backup_info['database'],
            'files' => $this->backup_info['files'],
            'status' => 'SUCCESS',
            'total_size' => $this->backup_info['compressed_size'] ?? $this->backup_info['files']['total_size']
        ];
        
        $report_file = $this->backup_dir . $this->backup_info['backup_id'] . '-REPORT.json';
        file_put_contents($report_file, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "   ✅ Report saved: {$report_file}\n";
    }
    
    /**
     * Restore from backup
     */
    public function restore_backup($backup_id, $options = []) {
        echo "🔄 SMO Social Backup Restoration\n";
        echo "=================================\n\n";
        
        echo "⚠️  WARNING: This will overwrite current data!\n";
        echo "Backup ID: {$backup_id}\n\n";
        
        // Add confirmation prompt for CLI
        if (php_sapi_name() === 'cli') {
            echo "Continue with restoration? (yes/no): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            if (trim(strtolower($line)) !== 'yes') {
                echo "Restoration cancelled.\n";
                return false;
            }
            fclose($handle);
        }
        
        try {
            $backup_file = $this->backup_dir . $backup_id . '.zip';
            
            if (!file_exists($backup_file)) {
                throw new Exception("Backup file not found: {$backup_file}");
            }
            
            // Extract backup
            $extract_dir = $this->backup_dir . 'restore-' . time() . '/';
            $zip = new ZipArchive();
            $zip->open($backup_file);
            $zip->extractTo($extract_dir);
            $zip->close();
            
            echo "✅ Backup extracted\n";
            
            // Restore files
            if (isset($options['files']) && $options['files']) {
                $this->restore_files($extract_dir . 'files/');
            }
            
            // Restore database
            if (isset($options['database']) && $options['database']) {
                $this->restore_database($extract_dir . 'database.sql');
            }
            
            echo "\n✅ Restoration completed successfully!\n";
            
            // Cleanup
            $this->remove_directory($extract_dir);
            
            return true;
            
        } catch (Exception $e) {
            echo "\n❌ Restoration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Restore files from backup
     */
    private function restore_files($source_dir) {
        echo "📁 Restoring files...\n";
        
        if (!is_dir($source_dir)) {
            throw new Exception("Files backup directory not found");
        }
        
        $this->copy_directory($source_dir, ABSPATH);
        echo "   ✅ Files restored\n";
    }
    
    /**
     * Restore database from backup
     */
    private function restore_database($sql_file) {
        echo "🗄️  Restoring database...\n";
        
        if (!file_exists($sql_file)) {
            throw new Exception("Database backup file not found");
        }
        
        global $wpdb;
        
        $sql = file_get_contents($sql_file);
        $queries = explode(';', $sql);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $wpdb->query($query);
            }
        }
        
        echo "   ✅ Database restored\n";
    }
    
    /**
     * List available backups
     */
    public function list_backups() {
        echo "💾 Available Backups\n";
        echo "===================\n\n";
        
        $files = glob($this->backup_dir . '*.zip');
        
        if (empty($files)) {
            echo "No backups found.\n";
            return;
        }
        
        foreach ($files as $file) {
            $filename = basename($file);
            $backup_id = str_replace('.zip', '', $filename);
            $size = $this->format_bytes(filesize($file));
            $date = date('Y-m-d H:i:s', filemtime($file));
            
            echo "📦 {$backup_id}\n";
            echo "   Date: {$date}\n";
            echo "   Size: {$size}\n";
            echo "   File: {$filename}\n\n";
        }
    }
    
    /**
     * Remove directory recursively
     */
    private function remove_directory($dir) {
        if (!is_dir($dir)) return;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Format bytes to human readable
     */
    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Log error message
     */
    private function log_error($message) {
        $log_entry = date('Y-m-d H:i:s') . ' - ERROR - ' . $message . "\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }
}

// CLI Execution
if (php_sapi_name() === 'cli') {
    $backup_manager = new SMO_Backup_Manager();
    
    $command = $argv[1] ?? 'backup';
    
    switch ($command) {
        case 'backup':
            $options = [
                'database' => in_array('--database', $argv),
                'files' => in_array('--files', $argv),
                'compress' => !in_array('--no-compress', $argv),
                'full_system' => in_array('--full-system', $argv),
                'remote_upload' => in_array('--remote', $argv)
            ];
            $backup_manager->run_backup($options);
            break;
            
        case 'restore':
            if (!isset($argv[2])) {
                echo "Usage: php production_backup_system.php restore <backup_id>\n";
                exit(1);
            }
            $backup_manager->restore_backup($argv[2]);
            break;
            
        case 'list':
            $backup_manager->list_backups();
            break;
            
        default:
            echo "Usage:\n";
            echo "  php production_backup_system.php backup [--database] [--files] [--full-system] [--remote] [--no-compress]\n";
            echo "  php production_backup_system.php restore <backup_id>\n";
            echo "  php production_backup_system.php list\n";
            break;
    }
    
    exit(0);
}

?>