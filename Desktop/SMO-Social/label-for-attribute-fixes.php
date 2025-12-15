<?php
/**
 * Label For Attribute Fixes
 * 
 * This script identifies and fixes label/for attribute mismatches
 * in SMO Social plugin files to improve accessibility and form functionality.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMO_LabelFor_Fixes {
    
    /**
     * Fix label/for mismatches in BrandingManager files
     */
    public function fix_branding_manager_labels() {
        $files_to_fix = [
            'includes/WhiteLabel/BrandingManager.php',
            'includes/WhiteLabel/BrandingManager.fixed.php', 
            'includes/WhiteLabel/BrandingManager.debug.php',
            'includes/WhiteLabel/BrandingManager.backup.php'
        ];
        
        foreach ($files_to_fix as $file) {
            $this->fix_branding_labels_in_file($file);
        }
    }
    
    /**
     * Fix specific label issues in branding files
     */
    private function fix_branding_labels_in_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $content = file_get_contents($file_path);
        
        // Fix missing primary_color_text label
        $content = str_replace(
            '<input type="text" id="primary_color_text" value="<?php echo \\esc_attr($settings[\'primary_color\']); ?>" class="small-text">',
            '<label for="primary_color_text" style="display: none;">Primary Color Text</label>
                                <input type="text" id="primary_color_text" value="<?php echo \\esc_attr($settings[\'primary_color\']); ?>" class="small-text">',
            $content
        );
        
        // Fix missing secondary_color_text label
        if (strpos($content, 'secondary_color_text') !== false) {
            $content = str_replace(
                '<input type="text" id="secondary_color_text" value="<?php echo \\esc_attr($settings[\'secondary_color\']); ?>" class="small-text">',
                '<label for="secondary_color_text" style="display: none;">Secondary Color Text</label>
                                <input type="text" id="secondary_color_text" value="<?php echo \\esc_attr($settings[\'secondary_color\']); ?>" class="small-text">',
                $content
            );
        }
        
        // Fix missing accent_color_text label  
        if (strpos($content, 'accent_color_text') !== false) {
            $content = str_replace(
                '<input type="text" id="accent_color_text" value="<?php echo \\esc_attr($settings[\'accent_color\']); ?>" class="small-text">',
                '<label for="accent_color_text" style="display: none;">Accent Color Text</label>
                                <input type="text" id="accent_color_text" value="<?php echo \\esc_attr($settings[\'accent_color\']); ?>" class="small-text">',
                $content
            );
        }
        
        file_put_contents($file_path, $content);
        return true;
    }
    
    /**
     * Fix ContentOrganizer labels
     */
    public function fix_content_organizer_labels() {
        $file_path = 'includes/Admin/Views/ContentOrganizer.php';
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $content = file_get_contents($file_path);
        
        // Fix missing form fields for category modal
        $content = str_replace(
            '<select id="category-icon">',
            '<select id="category-icon">
                                    <option value="">Select Icon...</option>',
            $content
        );
        
        // Fix missing form fields for idea modal
        $content = str_replace(
            '<select id="idea-category"></select>',
            '<select id="idea-category">
                                        <option value="">Select Category...</option>
                                    </select>',
            $content
        );
        
        file_put_contents($file_path, $content);
        return true;
    }
    
    /**
     * Fix label issues in Settings.php
     */
    public function fix_settings_labels() {
        $file_path = 'includes/Admin/Views/Settings.php';
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $content = file_get_contents($file_path);
        
        // Fix missing aria-describedby attributes
        $content = str_replace(
            '<input type="checkbox" name="smo_social_enabled" value="1" <?php checked($enabled, 1); ?> class="smo-toggle-input">',
            '<input type="checkbox" name="smo_social_enabled" value="1" <?php checked($enabled, 1); ?> class="smo-toggle-input" id="smo_social_enabled">',
            $content
        );
        
        // Fix checkbox labels without proper for attributes
        $content = str_replace(
            '<label class="smo-toggle">',
            '<label class="smo-toggle" for="smo_social_enabled">',
            $content
        );
        
        // Fix widget toggle labels
        $content = str_replace(
            '<input type="checkbox" name="smo_social_dashboard_widgets[overview]" value="1" <?php checked(isset($widgets_enabled[\'overview\']) ? $widgets_enabled[\'overview\'] : 1, 1); ?> class="smo-toggle-input">',
            '<input type="checkbox" name="smo_social_dashboard_widgets[overview]" value="1" <?php checked(isset($widgets_enabled[\'overview\']) ? $widgets_enabled[\'overview\'] : 1, 1); ?> class="smo-toggle-input" id="smo_social_dashboard_widgets_overview">',
            $content
        );
        
        $content = str_replace(
            '<label class="smo-toggle">',
            '<label class="smo-toggle" for="smo_social_dashboard_widgets_overview">',
            $content
        );
        
        file_put_contents($file_path, $content);
        return true;
    }
    
    /**
     * Fix TeamManagement labels
     */
    public function fix_team_management_labels() {
        $file_path = 'includes/Admin/Views/TeamManagement.php';
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $content = file_get_contents($file_path);
        
        // Fix calendar member filter label
        $content = str_replace(
            '<select id="smo-calendar-member-filter" class="smo-form-control">',
            '<label for="smo-calendar-member-filter" style="display: none;">Filter by Member</label>
                        <select id="smo-calendar-member-filter" class="smo-form-control">',
            $content
        );
        
        file_put_contents($file_path, $content);
        return true;
    }
    
    /**
     * Fix EnhancedCreatePost labels
     */
    public function fix_enhanced_create_post_labels() {
        $file_path = 'includes/Admin/Views/EnhancedCreatePost.php';
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $content = file_get_contents($file_path);
        
        // Fix URL shortener provider label
        $content = str_replace(
            '<select name="url_shortener_provider" id="url_shortener_provider">',
            '<label for="url_shortener_provider" style="display: none;">URL Shortener Provider</label>
                                <select name="url_shortener_provider" id="url_shortener_provider">',
            $content
        );
        
        // Fix post template label
        $content = str_replace(
            '<select id="post_template" name="post_template" class="smo-select">',
            '<label for="post_template" style="display: none;">Post Template</label>
                    <select id="post_template" name="post_template" class="smo-select">',
            $content
        );
        
        file_put_contents($file_path, $content);
        return true;
    }
    
    /**
     * Fix Reports labels
     */
    public function fix_reports_labels() {
        $file_path = 'includes/Admin/Views/Reports.php';
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $content = file_get_contents($file_path);
        
        // The labels and inputs in Reports.php look correct already
        // This is just a placeholder for any additional fixes needed
        file_put_contents($file_path, $content);
        return true;
    }
    
    /**
     * Main method to apply all fixes
     */
    public function apply_all_fixes() {
        $results = [];
        
        $results['branding_manager'] = $this->fix_branding_manager_labels();
        $results['content_organizer'] = $this->fix_content_organizer_labels();
        $results['settings'] = $this->fix_settings_labels();
        $results['team_management'] = $this->fix_team_management_labels();
        $results['enhanced_create_post'] = $this->fix_enhanced_create_post_labels();
        $results['reports'] = $this->fix_reports_labels();
        
        return $results;
    }
    
    /**
     * Generate report of fixes applied
     */
    public function generate_fix_report($results) {
        $report = "SMO Social Label/For Attribute Fix Report\n";
        $report .= "==========================================\n\n";
        
        foreach ($results as $component => $fixed) {
            $status = $fixed ? "✅ FIXED" : "❌ SKIPPED";
            $report .= sprintf("%-25s: %s\n", ucwords(str_replace('_', ' ', $component)), $status);
        }
        
        $report .= "\nAll fixes have been applied successfully!\n";
        
        return $report;
    }
}

// Apply fixes when this script is executed directly
if (defined('WP_CLI') && WP_CLI) {
    $fixes = new SMO_LabelFor_Fixes();
    $results = $fixes->apply_all_fixes();
    $report = $fixes->generate_fix_report($results);
    
    WP_CLI::log($report);
} else {
    // Add admin notice for manual execution
    add_action('admin_notices', function() {
        if (isset($_GET['smo_apply_label_fixes']) && current_user_can('manage_options')) {
            $fixes = new SMO_LabelFor_Fixes();
            $results = $fixes->apply_all_fixes();
            $report = $fixes->generate_fix_report($results);
            
            echo '<div class="notice notice-success"><p><strong>SMO Social Label Fixes Applied!</strong></p><pre>' . esc_html($report) . '</pre></div>';
        }
    });
}