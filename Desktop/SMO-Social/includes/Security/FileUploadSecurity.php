<?php
namespace SMO_Social\Security;

// Import global functions to avoid namespace issues
use function finfo_open;
use function finfo_file;
use function finfo_close;
use function mime_content_type;
use const FILEINFO_MIME_TYPE;

class FileUploadSecurity {
    private $allowed_extensions;
    private $max_file_size;
    private $allowed_mime_types;
    private $upload_directory;
    
    public function __construct() {
        $this->initialize_security_settings();
    }
    
    private function initialize_security_settings() {
        $this->allowed_extensions = array(
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', // Images
            'pdf', 'doc', 'docx', 'txt', 'rtf', // Documents
            'mp4', 'avi', 'mov', 'wmv', 'flv', // Videos
            'mp3', 'wav', 'ogg', 'm4a' // Audio
        );
        
        $this->allowed_mime_types = array(
            // Images
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
            // Documents
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'application/rtf',
            // Videos
            'video/mp4', 'video/avi', 'video/quicktime', 'video/x-ms-wmv', 'video/x-flv',
            // Audio
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'
        );
        
        $this->max_file_size = 10 * 1024 * 1024; // 10MB
        $this->upload_directory = SMO_SOCIAL_PLUGIN_DIR . 'uploads/';
    }
    
    public function validate_file_upload($file) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return array(
                'valid' => false,
                'error' => 'Invalid file upload'
            );
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return array(
                    'valid' => false,
                    'error' => 'No file uploaded'
                );
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return array(
                    'valid' => false,
                    'error' => 'File too large'
                );
            default:
                return array(
                    'valid' => false,
                    'error' => 'Unknown upload error'
                );
        }
        
        // Validate file size
        if ($file['size'] > $this->max_file_size) {
            return array(
                'valid' => false,
                'error' => 'File size exceeds maximum allowed size'
            );
        }
        
        // Validate file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_extensions)) {
            return array(
                'valid' => false,
                'error' => 'File extension not allowed'
            );
        }
        
        // Validate MIME type
        $mime_type = $this->get_mime_type($file['tmp_name']);
        if (!in_array($mime_type, $this->allowed_mime_types)) {
            return array(
                'valid' => false,
                'error' => 'File type not allowed'
            );
        }
        
        // Check for malicious content
        if ($this->contains_malicious_content($file['tmp_name'])) {
            return array(
                'valid' => false,
                'error' => 'File contains potentially malicious content'
            );
        }
        
        return array(
            'valid' => true,
            'file_info' => array(
                'extension' => $file_extension,
                'mime_type' => $mime_type,
                'size' => $file['size']
            )
        );
    }
    
    public function sanitize_filename($filename) {
        // Remove dangerous characters
        $dangerous_chars = array('\\', '/', ':', '*', '?', '"', '<', '>', '|');
        $sanitized = str_replace($dangerous_chars, '', $filename);
        
        // Remove directory traversal attempts
        $sanitized = str_replace(array('../', '..\\', './', '.\\'), '', $sanitized);
        
        // Remove multiple dots and replace spaces
        $sanitized = preg_replace('/[\.]+/', '.', $sanitized);
        $sanitized = preg_replace('/[\s]+/', '_', $sanitized);
        
        // Generate unique filename
        $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
        $name = pathinfo($sanitized, PATHINFO_FILENAME);
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        
        $unique_name = $name . '_' . uniqid() . '.' . $extension;
        
        return $unique_name;
    }
    
    public function move_uploaded_file($file, $destination) {
        $validation = $this->validate_file_upload($file);
        
        if (!$validation['valid']) {
            return $validation;
        }
        
        $sanitized_name = $this->sanitize_filename($file['name']);
        $destination_path = $this->upload_directory . $sanitized_name;
        
        // Ensure upload directory exists
        if (!file_exists($this->upload_directory)) {
            mkdir($this->upload_directory, 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $destination_path)) {
            // Set secure file permissions
            chmod($destination_path, 0644);
            
            return array(
                'success' => true,
                'file_path' => $destination_path,
                'file_name' => $sanitized_name,
                'file_info' => $validation['file_info']
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Failed to move uploaded file'
            );
        }
    }
    
    public function scan_for_viruses($file_path) {
        // Basic virus scanning using file signatures
        $file_content = file_get_contents($file_path);
        
        // Check for executable file signatures
        $executable_signatures = array(
            "\x4D\x5A", // PE header (Windows executables)
            "\x7F\x45\x4C\x46", // ELF header (Linux executables)
            "\xCE\xFA\xED\xFE", // Mach-O header (Mac executables)
            "\xCA\xFE\xBA\xBE", // Fat binary (Mac)
        );
        
        foreach ($executable_signatures as $signature) {
            if (strpos($file_content, $signature) !== false) {
                return array(
                    'clean' => false,
                    'threat' => 'Executable file detected'
                );
            }
        }
        
        // Check for script injection patterns
        $script_patterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript\s*:/i',
            '/data\s*:/i',
            '/vbscript\s*:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i'
        );
        
        if (preg_match('/\.(html|htm|xml|php|asp|jsp)$/i', $file_path)) {
            foreach ($script_patterns as $pattern) {
                if (preg_match($pattern, $file_content)) {
                    return array(
                        'clean' => false,
                        'threat' => 'Script injection detected'
                    );
                }
            }
        }
        
        return array('clean' => true);
    }
    
    public function get_file_dimensions($file_path) {
        if (!in_array(\mime_content_type($file_path), array('image/jpeg', 'image/png', 'image/gif'))) {
            return array('width' => 0, 'height' => 0);
        }

        list($width, $height) = \getimagesize($file_path);

        return array(
            'width' => $width ?: 0,
            'height' => $height ?: 0
        );
    }
    
    private function get_mime_type($file_path) {
        // Use multiple methods to detect MIME type
        $mime_type = null;

        // Method 1: finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_path);
            finfo_close($finfo);
        }

        // Method 2: mime_content_type
        if (!$mime_type && function_exists('mime_content_type')) {
            $mime_type = mime_content_type($file_path);
        }

        // Method 3: fallback to basic detection (duplicate for compatibility)
        if (!$mime_type && function_exists('mime_content_type')) {
            $mime_type = mime_content_type($file_path);
        }

        return $mime_type ?: 'application/octet-stream';
    }
    
    private function contains_malicious_content($file_path) {
        $file_content = file_get_contents($file_path);
        
        // Check for PHP code injection
        $php_patterns = array(
            '/<\?php/',
            '/<\?=/',
            '/<\?/',
            '/eval\s*\(/',
            '/assert\s*\(/',
            '/system\s*\(/',
            '/exec\s*\(/',
            '/shell_exec\s*\(/',
            '/passthru\s*\(/',
            '/file_get_contents\s*\(/',
            '/file_put_contents\s*\(/'
        );
        
        foreach ($php_patterns as $pattern) {
            if (preg_match($pattern, $file_content)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function cleanup_old_uploads($days = 30) {
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        if (!file_exists($this->upload_directory)) {
            return array('success' => true, 'files_removed' => 0);
        }
        
        $files_removed = 0;
        $directory = opendir($this->upload_directory);
        
        while (($file = readdir($directory)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $file_path = $this->upload_directory . $file;
            
            if (is_file($file_path) && filemtime($file_path) < $cutoff_time) {
                unlink($file_path);
                $files_removed++;
            }
        }
        
        closedir($directory);
        
        return array(
            'success' => true,
            'files_removed' => $files_removed
        );
    }
    
    public function get_upload_stats() {
        $stats = array(
            'total_files' => 0,
            'total_size' => 0,
            'by_extension' => array()
        );
        
        if (!file_exists($this->upload_directory)) {
            return $stats;
        }
        
        $directory = opendir($this->upload_directory);
        
        while (($file = readdir($directory)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $file_path = $this->upload_directory . $file;
            
            if (is_file($file_path)) {
                $stats['total_files']++;
                $stats['total_size'] += filesize($file_path);
                
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!isset($stats['by_extension'][$extension])) {
                    $stats['by_extension'][$extension] = 0;
                }
                $stats['by_extension'][$extension]++;
            }
        }
        
        closedir($directory);
        
        return $stats;
    }

    // Alias methods for test compatibility
    public function validate_file_type($filename, $mime_type) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $this->allowed_extensions) && in_array($mime_type, $this->allowed_mime_types);
    }

    public function check_file_size($size) {
        return $size <= $this->max_file_size;
    }

    public function scan_for_malware($file_path) {
        return $this->scan_for_viruses($file_path);
    }
}
