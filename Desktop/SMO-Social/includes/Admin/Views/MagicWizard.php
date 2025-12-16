<?php
/**
 * SMO Social Magic Wizard
 * 
 * A step-by-step setup wizard that helps users quickly configure the plugin
 * according to their preferences and needs without extensive manual setup.
 * 
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Magic Wizard Class
 */
class MagicWizard
{
    /**
     * Render the Magic Wizard interface
     */
    public function render()
    {
        // Get current settings to pre-fill the wizard
        $current_settings = $this->get_current_settings();
        
        // Get available AI providers
        $ai_providers = $this->get_available_ai_providers();
        
        // Get available social platforms
        $social_platforms = $this->get_available_social_platforms();
        
        // Get user information
        $current_user = wp_get_current_user();
        
        // Render the wizard HTML
        ?>
        <div class="smo-magic-wizard-wrapper">
            <div class="smo-wizard-header">
                <h2 class="smo-wizard-title">
                    <span class="dashicons dashicons-superhero"></span>
                    <?php esc_html_e('Magic Setup Wizard', 'smo-social'); ?>
                </h2>
                <p class="smo-wizard-subtitle">
                    <?php esc_html_e('Quickly configure SMO Social according to your preferences and needs', 'smo-social'); ?>
                </p>
                <div class="smo-wizard-progress">
                    <div class="smo-progress-bar" id="smo-wizard-progress"></div>
                </div>
            </div>
            
            <div class="smo-wizard-container">
                <!-- Wizard Step 1: Welcome & User Info -->
                <div class="smo-wizard-step active" id="wizard-step-1">
                    <div class="smo-step-header">
                        <h3><?php esc_html_e('Welcome to SMO Social!', 'smo-social'); ?></h3>
                        <p><?php esc_html_e('Let\'s get started by telling us a bit about yourself and your goals.', 'smo-social'); ?></p>
                    </div>
                    
                    <div class="smo-form-grid two-columns">
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_user_name">
                                <?php esc_html_e('Your Name', 'smo-social'); ?>
                            </label>
                            <input type="text" id="wizard_user_name" 
                                   name="wizard_user_name" 
                                   class="smo-input" 
                                   value="<?php echo esc_attr($current_user->display_name); ?>"
                                   placeholder="<?php esc_attr_e('Enter your name', 'smo-social'); ?>">
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_user_email">
                                <?php esc_html_e('Email Address', 'smo-social'); ?>
                            </label>
                            <input type="email" id="wizard_user_email" 
                                   name="wizard_user_email" 
                                   class="smo-input" 
                                   value="<?php echo esc_attr($current_user->user_email); ?>"
                                   placeholder="<?php esc_attr_e('Enter your email', 'smo-social'); ?>">
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_user_role">
                                <?php esc_html_e('Your Role', 'smo-social'); ?>
                            </label>
                            <select id="wizard_user_role" name="wizard_user_role" class="smo-select">
                                <option value="business_owner" <?php selected($current_settings['user_role'], 'business_owner'); ?>>
                                    <?php esc_html_e('Business Owner', 'smo-social'); ?>
                                </option>
                                <option value="marketing_manager" <?php selected($current_settings['user_role'], 'marketing_manager'); ?>>
                                    <?php esc_html_e('Marketing Manager', 'smo-social'); ?>
                                </option>
                                <option value="social_media_manager" <?php selected($current_settings['user_role'], 'social_media_manager'); ?>>
                                    <?php esc_html_e('Social Media Manager', 'smo-social'); ?>
                                </option>
                                <option value="content_creator" <?php selected($current_settings['user_role'], 'content_creator'); ?>>
                                    <?php esc_html_e('Content Creator', 'smo-social'); ?>
                                </option>
                                <option value="agency" <?php selected($current_settings['user_role'], 'agency'); ?>>
                                    <?php esc_html_e('Agency/Consultant', 'smo-social'); ?>
                                </option>
                                <option value="other" <?php selected($current_settings['user_role'], 'other'); ?>>
                                    <?php esc_html_e('Other', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_business_type">
                                <?php esc_html_e('Business Type', 'smo-social'); ?>
                            </label>
                            <select id="wizard_business_type" name="wizard_business_type" class="smo-select">
                                <option value="ecommerce" <?php selected($current_settings['business_type'], 'ecommerce'); ?>>
                                    <?php esc_html_e('E-commerce', 'smo-social'); ?>
                                </option>
                                <option value="local_business" <?php selected($current_settings['business_type'], 'local_business'); ?>>
                                    <?php esc_html_e('Local Business', 'smo-social'); ?>
                                </option>
                                <option value="blog_personal" <?php selected($current_settings['business_type'], 'blog_personal'); ?>>
                                    <?php esc_html_e('Blog/Personal Brand', 'smo-social'); ?>
                                </option>
                                <option value="saas_tech" <?php selected($current_settings['business_type'], 'saas_tech'); ?>>
                                    <?php esc_html_e('SaaS/Tech Company', 'smo-social'); ?>
                                </option>
                                <option value="nonprofit" <?php selected($current_settings['business_type'], 'nonprofit'); ?>>
                                    <?php esc_html_e('Non-profit Organization', 'smo-social'); ?>
                                </option>
                                <option value="other" <?php selected($current_settings['business_type'], 'other'); ?>>
                                    <?php esc_html_e('Other', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_primary_goal">
                                <?php esc_html_e('Primary Goal', 'smo-social'); ?>
                            </label>
                            <select id="wizard_primary_goal" name="wizard_primary_goal" class="smo-select">
                                <option value="brand_awareness" <?php selected($current_settings['primary_goal'], 'brand_awareness'); ?>>
                                    <?php esc_html_e('Brand Awareness', 'smo-social'); ?>
                                </option>
                                <option value="lead_generation" <?php selected($current_settings['primary_goal'], 'lead_generation'); ?>>
                                    <?php esc_html_e('Lead Generation', 'smo-social'); ?>
                                </option>
                                <option value="sales_conversion" <?php selected($current_settings['primary_goal'], 'sales_conversion'); ?>>
                                    <?php esc_html_e('Sales & Conversions', 'smo-social'); ?>
                                </option>
                                <option value="community_building" <?php selected($current_settings['primary_goal'], 'community_building'); ?>>
                                    <?php esc_html_e('Community Building', 'smo-social'); ?>
                                </option>
                                <option value="customer_support" <?php selected($current_settings['primary_goal'], 'customer_support'); ?>>
                                    <?php esc_html_e('Customer Support', 'smo-social'); ?>
                                </option>
                                <option value="content_distribution" <?php selected($current_settings['primary_goal'], 'content_distribution'); ?>>
                                    <?php esc_html_e('Content Distribution', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_experience_level">
                                <?php esc_html_e('Social Media Experience', 'smo-social'); ?>
                            </label>
                            <select id="wizard_experience_level" name="wizard_experience_level" class="smo-select">
                                <option value="beginner" <?php selected($current_settings['experience_level'], 'beginner'); ?>>
                                    <?php esc_html_e('Beginner', 'smo-social'); ?>
                                </option>
                                <option value="intermediate" <?php selected($current_settings['experience_level'], 'intermediate'); ?>>
                                    <?php esc_html_e('Intermediate', 'smo-social'); ?>
                                </option>
                                <option value="advanced" <?php selected($current_settings['experience_level'], 'advanced'); ?>>
                                    <?php esc_html_e('Advanced', 'smo-social'); ?>
                                </option>
                                <option value="expert" <?php selected($current_settings['experience_level'], 'expert'); ?>>
                                    <?php esc_html_e('Expert', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="smo-wizard-actions">
                        <button class="smo-btn smo-btn-primary" onclick="smoWizardNextStep(2)">
                            <?php esc_html_e('Continue to AI Setup', 'smo-social'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Wizard Step 2: AI Configuration -->
                <div class="smo-wizard-step" id="wizard-step-2">
                    <div class="smo-step-header">
                        <h3><?php esc_html_e('AI Configuration', 'smo-social'); ?></h3>
                        <p><?php esc_html_e('Configure your AI preferences for content generation and optimization.', 'smo-social'); ?></p>
                    </div>
                    
                    <div class="smo-form-grid two-columns">
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_ai_enabled">
                                <?php esc_html_e('Enable AI Features', 'smo-social'); ?>
                            </label>
                            <label class="smo-toggle">
                                <input type="checkbox" id="wizard_ai_enabled" 
                                       name="wizard_ai_enabled" 
                                       class="smo-toggle-input" 
                                       value="1" <?php checked($current_settings['ai_enabled'], 1); ?>>
                                <span class="smo-toggle-switch"></span>
                                <span class="smo-toggle-label">
                                    <?php esc_html_e('Enable AI-powered content optimization and generation', 'smo-social'); ?>
                                </span>
                            </label>
                        </div>
                        
                        <div class="smo-form-field" id="wizard_ai_provider_field">
                            <label class="smo-form-label" for="wizard_ai_provider">
                                <?php esc_html_e('Primary AI Provider', 'smo-social'); ?>
                            </label>
                            <select id="wizard_ai_provider" name="wizard_ai_provider" class="smo-select">
                                <?php foreach ($ai_providers as $provider_id => $provider_name): ?>
                                    <option value="<?php echo esc_attr($provider_id); ?>" 
                                        <?php selected($current_settings['ai_provider'], $provider_id); ?>>
                                        <?php echo esc_html($provider_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="smo-form-field" id="wizard_ai_tone_field">
                            <label class="smo-form-label" for="wizard_ai_tone">
                                <?php esc_html_e('Content Tone', 'smo-social'); ?>
                            </label>
                            <select id="wizard_ai_tone" name="wizard_ai_tone" class="smo-select">
                                <option value="professional" <?php selected($current_settings['ai_tone'], 'professional'); ?>>
                                    <?php esc_html_e('Professional', 'smo-social'); ?>
                                </option>
                                <option value="casual" <?php selected($current_settings['ai_tone'], 'casual'); ?>>
                                    <?php esc_html_e('Casual', 'smo-social'); ?>
                                </option>
                                <option value="friendly" <?php selected($current_settings['ai_tone'], 'friendly'); ?>>
                                    <?php esc_html_e('Friendly', 'smo-social'); ?>
                                </option>
                                <option value="authoritative" <?php selected($current_settings['ai_tone'], 'authoritative'); ?>>
                                    <?php esc_html_e('Authoritative', 'smo-social'); ?>
                                </option>
                                <option value="humorous" <?php selected($current_settings['ai_tone'], 'humorous'); ?>>
                                    <?php esc_html_e('Humorous', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_ai_variants">
                                <?php esc_html_e('Generate Content Variants', 'smo-social'); ?>
                            </label>
                            <label class="smo-toggle">
                                <input type="checkbox" id="wizard_ai_variants" 
                                       name="wizard_ai_variants" 
                                       class="smo-toggle-input" 
                                       value="1" <?php checked($current_settings['ai_variants'], 1); ?>>
                                <span class="smo-toggle-switch"></span>
                                <span class="smo-toggle-label">
                                    <?php esc_html_e('Automatically generate multiple content variants for A/B testing', 'smo-social'); ?>
                                </span>
                            </label>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_auto_hashtags">
                                <?php esc_html_e('Auto-Generate Hashtags', 'smo-social'); ?>
                            </label>
                            <label class="smo-toggle">
                                <input type="checkbox" id="wizard_auto_hashtags" 
                                       name="wizard_auto_hashtags" 
                                       class="smo-toggle-input" 
                                       value="1" <?php checked($current_settings['auto_hashtags'], 1); ?>>
                                <span class="smo-toggle-switch"></span>
                                <span class="smo-toggle-label">
                                    <?php esc_html_e('Automatically generate relevant hashtags for your content', 'smo-social'); ?>
                                </span>
                            </label>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_auto_scheduling">
                                <?php esc_html_e('AI-Powered Scheduling', 'smo-social'); ?>
                            </label>
                            <label class="smo-toggle">
                                <input type="checkbox" id="wizard_auto_scheduling" 
                                       name="wizard_auto_scheduling" 
                                       class="smo-toggle-input" 
                                       value="1" <?php checked($current_settings['auto_scheduling'], 1); ?>>
                                <span class="smo-toggle-switch"></span>
                                <span class="smo-toggle-label">
                                    <?php esc_html_e('Let AI determine the best posting times for maximum engagement', 'smo-social'); ?>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="smo-wizard-actions">
                        <button class="smo-btn smo-btn-secondary" onclick="smoWizardPrevStep(1)">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <?php esc_html_e('Back', 'smo-social'); ?>
                        </button>
                        <button class="smo-btn smo-btn-primary" onclick="smoWizardNextStep(3)">
                            <?php esc_html_e('Continue to Platform Setup', 'smo-social'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Wizard Step 3: Platform Configuration -->
                <div class="smo-wizard-step" id="wizard-step-3">
                    <div class="smo-step-header">
                        <h3><?php esc_html_e('Platform Configuration', 'smo-social'); ?></h3>
                        <p><?php esc_html_e('Select the social media platforms you want to connect and manage.', 'smo-social'); ?></p>
                    </div>
                    
                    <div class="smo-platform-grid">
                        <?php foreach ($social_platforms as $platform_id => $platform_name): ?>
                            <div class="smo-platform-card">
                                <label class="smo-platform-label">
                                    <input type="checkbox" 
                                           name="wizard_platforms[]" 
                                           value="<?php echo esc_attr($platform_id); ?>"
                                           class="smo-platform-checkbox" 
                                           <?php checked(in_array($platform_id, $current_settings['platforms'])); ?>>
                                    <span class="smo-platform-icon">
                                        <?php echo $this->get_platform_icon($platform_id); ?>
                                    </span>
                                    <span class="smo-platform-name">
                                        <?php echo esc_html($platform_name); ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="smo-form-field">
                        <label class="smo-form-label" for="wizard_auto_connect">
                            <?php esc_html_e('Auto-Connect Platforms', 'smo-social'); ?>
                        </label>
                        <label class="smo-toggle">
                            <input type="checkbox" id="wizard_auto_connect" 
                                   name="wizard_auto_connect" 
                                   class="smo-toggle-input" 
                                   value="1" <?php checked($current_settings['auto_connect'], 1); ?>>
                            <span class="smo-toggle-switch"></span>
                            <span class="smo-toggle-label">
                                <?php esc_html_e('Automatically attempt to connect selected platforms (if credentials are available)', 'smo-social'); ?>
                            </span>
                        </label>
                    </div>
                    
                    <div class="smo-wizard-actions">
                        <button class="smo-btn smo-btn-secondary" onclick="smoWizardPrevStep(2)">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <?php esc_html_e('Back', 'smo-social'); ?>
                        </button>
                        <button class="smo-btn smo-btn-primary" onclick="smoWizardNextStep(4)">
                            <?php esc_html_e('Continue to Content Preferences', 'smo-social'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Wizard Step 4: Content Preferences -->
                <div class="smo-wizard-step" id="wizard-step-4">
                    <div class="smo-step-header">
                        <h3><?php esc_html_e('Content Preferences', 'smo-social'); ?></h3>
                        <p><?php esc_html_e('Configure your content creation and posting preferences.', 'smo-social'); ?></p>
                    </div>
                    
                    <div class="smo-form-grid two-columns">
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_content_types">
                                <?php esc_html_e('Preferred Content Types', 'smo-social'); ?>
                            </label>
                            <select id="wizard_content_types" name="wizard_content_types[]" 
                                    class="smo-select" multiple style="height: 120px;">
                                <option value="text_posts" <?php selected(in_array('text_posts', $current_settings['content_types'])); ?>>
                                    <?php esc_html_e('Text Posts', 'smo-social'); ?>
                                </option>
                                <option value="images" <?php selected(in_array('images', $current_settings['content_types'])); ?>>
                                    <?php esc_html_e('Images & Graphics', 'smo-social'); ?>
                                </option>
                                <option value="videos" <?php selected(in_array('videos', $current_settings['content_types'])); ?>>
                                    <?php esc_html_e('Videos', 'smo-social'); ?>
                                </option>
                                <option value="links" <?php selected(in_array('links', $current_settings['content_types'])); ?>>
                                    <?php esc_html_e('Links & Articles', 'smo-social'); ?>
                                </option>
                                <option value="polls" <?php selected(in_array('polls', $current_settings['content_types'])); ?>>
                                    <?php esc_html_e('Polls & Surveys', 'smo-social'); ?>
                                </option>
                                <option value="stories" <?php selected(in_array('stories', $current_settings['content_types'])); ?>>
                                    <?php esc_html_e('Stories & Ephemeral Content', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_posting_frequency">
                                <?php esc_html_e('Posting Frequency', 'smo-social'); ?>
                            </label>
                            <select id="wizard_posting_frequency" name="wizard_posting_frequency" class="smo-select">
                                <option value="daily" <?php selected($current_settings['posting_frequency'], 'daily'); ?>>
                                    <?php esc_html_e('Daily', 'smo-social'); ?>
                                </option>
                                <option value="weekly" <?php selected($current_settings['posting_frequency'], 'weekly'); ?>>
                                    <?php esc_html_e('Weekly (3-5 times)', 'smo-social'); ?>
                                </option>
                                <option value="biweekly" <?php selected($current_settings['posting_frequency'], 'biweekly'); ?>>
                                    <?php esc_html_e('Bi-weekly (1-2 times)', 'smo-social'); ?>
                                </option>
                                <option value="monthly" <?php selected($current_settings['posting_frequency'], 'monthly'); ?>>
                                    <?php esc_html_e('Monthly (4-8 times)', 'smo-social'); ?>
                                </option>
                                <option value="custom" <?php selected($current_settings['posting_frequency'], 'custom'); ?>>
                                    <?php esc_html_e('Custom Schedule', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_content_sources">
                                <?php esc_html_e('Content Sources', 'smo-social'); ?>
                            </label>
                            <select id="wizard_content_sources" name="wizard_content_sources[]" 
                                    class="smo-select" multiple style="height: 120px;">
                                <option value="original" <?php selected(in_array('original', $current_settings['content_sources'])); ?>>
                                    <?php esc_html_e('Original Content', 'smo-social'); ?>
                                </option>
                                <option value="curated" <?php selected(in_array('curated', $current_settings['content_sources'])); ?>>
                                    <?php esc_html_e('Curated Content', 'smo-social'); ?>
                                </option>
                                <option value="user_generated" <?php selected(in_array('user_generated', $current_settings['content_sources'])); ?>>
                                    <?php esc_html_e('User-Generated Content', 'smo-social'); ?>
                                </option>
                                <option value="rss_import" <?php selected(in_array('rss_import', $current_settings['content_sources'])); ?>>
                                    <?php esc_html_e('RSS Feed Import', 'smo-social'); ?>
                                </option>
                                <option value="ai_generated" <?php selected(in_array('ai_generated', $current_settings['content_sources'])); ?>>
                                    <?php esc_html_e('AI-Generated Content', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_timezone">
                                <?php esc_html_e('Default Timezone', 'smo-social'); ?>
                            </label>
                            <select id="wizard_timezone" name="wizard_timezone" class="smo-select">
                                <?php echo wp_timezone_choice($current_settings['timezone']); ?>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_date_format">
                                <?php esc_html_e('Date Format', 'smo-social'); ?>
                            </label>
                            <select id="wizard_date_format" name="wizard_date_format" class="smo-select">
                                <option value="Y-m-d H:i:s" <?php selected($current_settings['date_format'], 'Y-m-d H:i:s'); ?>>
                                    2024-11-24 14:30:00
                                </option>
                                <option value="m/d/Y H:i" <?php selected($current_settings['date_format'], 'm/d/Y H:i'); ?>>
                                    11/24/2024 14:30
                                </option>
                                <option value="d/m/Y H:i" <?php selected($current_settings['date_format'], 'd/m/Y H:i'); ?>>
                                    24/11/2024 14:30
                                </option>
                                <option value="F j, Y g:i A" <?php selected($current_settings['date_format'], 'F j, Y g:i A'); ?>>
                                    November 24, 2024 2:30 PM
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_enable_analytics">
                                <?php esc_html_e('Enable Analytics Tracking', 'smo-social'); ?>
                            </label>
                            <label class="smo-toggle">
                                <input type="checkbox" id="wizard_enable_analytics" 
                                       name="wizard_enable_analytics" 
                                       class="smo-toggle-input" 
                                       value="1" <?php checked($current_settings['enable_analytics'], 1); ?>>
                                <span class="smo-toggle-switch"></span>
                                <span class="smo-toggle-label">
                                    <?php esc_html_e('Track post performance and engagement metrics', 'smo-social'); ?>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="smo-wizard-actions">
                        <button class="smo-btn smo-btn-secondary" onclick="smoWizardPrevStep(3)">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <?php esc_html_e('Back', 'smo-social'); ?>
                        </button>
                        <button class="smo-btn smo-btn-primary" onclick="smoWizardNextStep(5)">
                            <?php esc_html_e('Continue to Advanced Options', 'smo-social'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Wizard Step 5: Advanced Options -->
                <div class="smo-wizard-step" id="wizard-step-5">
                    <div class="smo-step-header">
                        <h3><?php esc_html_e('Advanced Options', 'smo-social'); ?></h3>
                        <p><?php esc_html_e('Configure advanced settings for power users.', 'smo-social'); ?></p>
                    </div>
                    
                    <div class="smo-form-grid two-columns">
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_enable_approval_workflow">
                                <?php esc_html_e('Approval Workflow', 'smo-social'); ?>
                            </label>
                            <label class="smo-toggle">
                                <input type="checkbox" id="wizard_enable_approval_workflow" 
                                       name="wizard_enable_approval_workflow" 
                                       class="smo-toggle-input" 
                                       value="1" <?php checked($current_settings['enable_approval_workflow'], 1); ?>>
                                <span class="smo-toggle-switch"></span>
                                <span class="smo-toggle-label">
                                    <?php esc_html_e('Require approval for posts before publishing', 'smo-social'); ?>
                                </span>
                            </label>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_enable_team_collaboration">
                                <?php esc_html_e('Team Collaboration', 'smo-social'); ?>
                            </label>
                            <label class="smo-toggle">
                                <input type="checkbox" id="wizard_enable_team_collaboration" 
                                       name="wizard_enable_team_collaboration" 
                                       class="smo-toggle-input" 
                                       value="1" <?php checked($current_settings['enable_team_collaboration'], 1); ?>>
                                <span class="smo-toggle-switch"></span>
                                <span class="smo-toggle-label">
                                    <?php esc_html_e('Enable team collaboration features', 'smo-social'); ?>
                                </span>
                            </label>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_data_retention">
                                <?php esc_html_e('Data Retention Period', 'smo-social'); ?>
                            </label>
                            <select id="wizard_data_retention" name="wizard_data_retention" class="smo-select">
                                <option value="30" <?php selected($current_settings['data_retention'], '30'); ?>>
                                    <?php esc_html_e('30 days', 'smo-social'); ?>
                                </option>
                                <option value="90" <?php selected($current_settings['data_retention'], '90'); ?>>
                                    <?php esc_html_e('90 days', 'smo-social'); ?>
                                </option>
                                <option value="180" <?php selected($current_settings['data_retention'], '180'); ?>>
                                    <?php esc_html_e('180 days', 'smo-social'); ?>
                                </option>
                                <option value="365" <?php selected($current_settings['data_retention'], '365'); ?>>
                                    <?php esc_html_e('1 year', 'smo-social'); ?>
                                </option>
                                <option value="0" <?php selected($current_settings['data_retention'], '0'); ?>>
                                    <?php esc_html_e('Forever', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_log_level">
                                <?php esc_html_e('Log Level', 'smo-social'); ?>
                            </label>
                            <select id="wizard_log_level" name="wizard_log_level" class="smo-select">
                                <option value="error" <?php selected($current_settings['log_level'], 'error'); ?>>
                                    <?php esc_html_e('Errors only', 'smo-social'); ?>
                                </option>
                                <option value="warning" <?php selected($current_settings['log_level'], 'warning'); ?>>
                                    <?php esc_html_e('Warnings and errors', 'smo-social'); ?>
                                </option>
                                <option value="info" <?php selected($current_settings['log_level'], 'info'); ?>>
                                    <?php esc_html_e('Information, warnings, and errors', 'smo-social'); ?>
                                </option>
                                <option value="debug" <?php selected($current_settings['log_level'], 'debug'); ?>>
                                    <?php esc_html_e('Debug mode (very verbose)', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_enable_backups">
                                <?php esc_html_e('Automatic Backups', 'smo-social'); ?>
                            </label>
                            <label class="smo-toggle">
                                <input type="checkbox" id="wizard_enable_backups" 
                                       name="wizard_enable_backups" 
                                       class="smo-toggle-input" 
                                       value="1" <?php checked($current_settings['enable_backups'], 1); ?>>
                                <span class="smo-toggle-switch"></span>
                                <span class="smo-toggle-label">
                                    <?php esc_html_e('Automatically backup settings and content', 'smo-social'); ?>
                                </span>
                            </label>
                        </div>
                        
                        <div class="smo-form-field">
                            <label class="smo-form-label" for="wizard_enable_notifications">
                                <?php esc_html_e('Email Notifications', 'smo-social'); ?>
                            </label>
                            <label class="smo-toggle">
                                <input type="checkbox" id="wizard_enable_notifications" 
                                       name="wizard_enable_notifications" 
                                       class="smo-toggle-input" 
                                       value="1" <?php checked($current_settings['enable_notifications'], 1); ?>>
                                <span class="smo-toggle-switch"></span>
                                <span class="smo-toggle-label">
                                    <?php esc_html_e('Send email notifications for important events', 'smo-social'); ?>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="smo-wizard-actions">
                        <button class="smo-btn smo-btn-secondary" onclick="smoWizardPrevStep(4)">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <?php esc_html_e('Back', 'smo-social'); ?>
                        </button>
                        <button class="smo-btn smo-btn-primary" onclick="smoWizardNextStep(6)">
                            <?php esc_html_e('Continue to Review', 'smo-social'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Wizard Step 6: Review & Complete -->
                <div class="smo-wizard-step" id="wizard-step-6">
                    <div class="smo-step-header">
                        <h3><?php esc_html_e('Review & Complete Setup', 'smo-social'); ?></h3>
                        <p><?php esc_html_e('Review your configuration and complete the setup process.', 'smo-social'); ?></p>
                    </div>
                    
                    <div class="smo-review-summary">
                        <div class="smo-review-section">
                            <h4><?php esc_html_e('User Information', 'smo-social'); ?></h4>
                            <div class="smo-review-content" id="review-user-info">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="smo-review-section">
                            <h4><?php esc_html_e('AI Configuration', 'smo-social'); ?></h4>
                            <div class="smo-review-content" id="review-ai-config">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="smo-review-section">
                            <h4><?php esc_html_e('Platform Configuration', 'smo-social'); ?></h4>
                            <div class="smo-review-content" id="review-platform-config">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="smo-review-section">
                            <h4><?php esc_html_e('Content Preferences', 'smo-social'); ?></h4>
                            <div class="smo-review-content" id="review-content-prefs">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="smo-review-section">
                            <h4><?php esc_html_e('Advanced Options', 'smo-social'); ?></h4>
                            <div class="smo-review-content" id="review-advanced-opts">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="smo-wizard-actions">
                        <button class="smo-btn smo-btn-secondary" onclick="smoWizardPrevStep(5)">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <?php esc_html_e('Back', 'smo-social'); ?>
                        </button>
                        <button class="smo-btn smo-btn-success" id="smo-complete-wizard" 
                                onclick="smoCompleteWizard()">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e('Complete Setup', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Wizard Completion Message -->
                <div class="smo-wizard-completion" id="wizard-completion" style="display: none;">
                    <div class="smo-completion-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <h3><?php esc_html_e('Setup Complete!', 'smo-social'); ?></h3>
                    <p><?php esc_html_e('Your SMO Social plugin has been successfully configured according to your preferences.', 'smo-social'); ?></p>
                    <p><?php esc_html_e('You can now start creating and scheduling content right away!', 'smo-social'); ?></p>
                    
                    <div class="smo-completion-actions">
                        <a href="<?php echo admin_url('admin.php?page=smo-social-create'); ?>" 
                           class="smo-btn smo-btn-primary">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php esc_html_e('Create First Post', 'smo-social'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=smo-social'); ?>" 
                           class="smo-btn smo-btn-secondary">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php esc_html_e('Go to Dashboard', 'smo-social'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Wizard JavaScript -->
        <script>
        function smoWizardNextStep(stepNumber) {
            // Validate current step before proceeding
            if (!smoValidateCurrentStep()) {
                return;
            }
            
            // Hide all steps
            var steps = document.querySelectorAll('.smo-wizard-step');
            steps.forEach(function(step) {
                step.style.display = 'none';
                step.classList.remove('active');
            });
            
            // Show selected step
            var nextStep = document.getElementById('wizard-step-' + stepNumber);
            if (nextStep) {
                nextStep.style.display = 'block';
                nextStep.classList.add('active');
                
                // Update progress bar
                var progress = ((stepNumber - 1) / 5) * 100;
                document.getElementById('smo-wizard-progress').style.width = progress + '%';
                
                // If this is the review step, populate the review content
                if (stepNumber === 6) {
                    smoPopulateReviewContent();
                }
            }
        }
        
        function smoWizardPrevStep(stepNumber) {
            // Hide all steps
            var steps = document.querySelectorAll('.smo-wizard-step');
            steps.forEach(function(step) {
                step.style.display = 'none';
                step.classList.remove('active');
            });
            
            // Show selected step
            var prevStep = document.getElementById('wizard-step-' + stepNumber);
            if (prevStep) {
                prevStep.style.display = 'block';
                prevStep.classList.add('active');
                
                // Update progress bar
                var progress = ((stepNumber - 1) / 5) * 100;
                document.getElementById('smo-wizard-progress').style.width = progress + '%';
            }
        }
        
        function smoValidateCurrentStep() {
            // Get current active step
            var activeStep = document.querySelector('.smo-wizard-step.active');
            if (!activeStep) return true;
            
            var stepId = activeStep.id;
            
            // Step 1 validation
            if (stepId === 'wizard-step-1') {
                var userName = document.getElementById('wizard_user_name').value;
                var userEmail = document.getElementById('wizard_user_email').value;
                
                if (!userName || !userEmail) {
                    alert('<?php esc_html_e("Please fill in your name and email address.", "smo-social"); ?>');
                    return false;
                }
                
                if (!isValidEmail(userEmail)) {
                    alert('<?php esc_html_e("Please enter a valid email address.", "smo-social"); ?>');
                    return false;
                }
            }
            
            // Step 3 validation (platform selection)
            if (stepId === 'wizard-step-3') {
                var checkboxes = document.querySelectorAll('#wizard-step-3 input[type="checkbox"]:checked');
                if (checkboxes.length === 0) {
                    if (!confirm('<?php esc_html_e("You haven\'t selected any platforms. Are you sure you want to continue?", "smo-social"); ?>')) {
                        return false;
                    }
                }
            }
            
            return true;
        }
        
        function isValidEmail(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function smoPopulateReviewContent() {
            // User Information
            var userName = document.getElementById('wizard_user_name').value;
            var userEmail = document.getElementById('wizard_user_email').value;
            var userRole = document.getElementById('wizard_user_role').options[
                document.getElementById('wizard_user_role').selectedIndex
            ].text;
            var businessType = document.getElementById('wizard_business_type').options[
                document.getElementById('wizard_business_type').selectedIndex
            ].text;
            var primaryGoal = document.getElementById('wizard_primary_goal').options[
                document.getElementById('wizard_primary_goal').selectedIndex
            ].text;
            var experienceLevel = document.getElementById('wizard_experience_level').options[
                document.getElementById('wizard_experience_level').selectedIndex
            ].text;
            
            var userInfoHtml = '<p><strong>Name:</strong> ' + userName + '</p>' +
                              '<p><strong>Email:</strong> ' + userEmail + '</p>' +
                              '<p><strong>Role:</strong> ' + userRole + '</p>' +
                              '<p><strong>Business Type:</strong> ' + businessType + '</p>' +
                              '<p><strong>Primary Goal:</strong> ' + primaryGoal + '</p>' +
                              '<p><strong>Experience Level:</strong> ' + experienceLevel + '</p>';
            
            document.getElementById('review-user-info').innerHTML = userInfoHtml;
            
            // AI Configuration
            var aiEnabled = document.getElementById('wizard_ai_enabled').checked;
            var aiProvider = document.getElementById('wizard_ai_provider').options[
                document.getElementById('wizard_ai_provider').selectedIndex
            ].text;
            var aiTone = document.getElementById('wizard_ai_tone').options[
                document.getElementById('wizard_ai_tone').selectedIndex
            ].text;
            var aiVariants = document.getElementById('wizard_ai_variants').checked;
            var autoHashtags = document.getElementById('wizard_auto_hashtags').checked;
            var autoScheduling = document.getElementById('wizard_auto_scheduling').checked;
            
            var aiConfigHtml = '<p><strong>AI Enabled:</strong> ' + (aiEnabled ? 'Yes' : 'No') + '</p>';
            if (aiEnabled) {
                aiConfigHtml += '<p><strong>Provider:</strong> ' + aiProvider + '</p>' +
                               '<p><strong>Tone:</strong> ' + aiTone + '</p>' +
                               '<p><strong>Content Variants:</strong> ' + (aiVariants ? 'Yes' : 'No') + '</p>' +
                               '<p><strong>Auto Hashtags:</strong> ' + (autoHashtags ? 'Yes' : 'No') + '</p>' +
                               '<p><strong>AI Scheduling:</strong> ' + (autoScheduling ? 'Yes' : 'No') + '</p>';
            }
            
            document.getElementById('review-ai-config').innerHTML = aiConfigHtml;
            
            // Platform Configuration
            var checkboxes = document.querySelectorAll('#wizard-step-3 input[type="checkbox"]:checked');
            var platforms = [];
            checkboxes.forEach(function(checkbox) {
                var label = checkbox.closest('.smo-platform-label');
                if (label) {
                    var platformName = label.querySelector('.smo-platform-name').textContent;
                    platforms.push(platformName);
                }
            });
            
            var platformConfigHtml = '<p><strong>Selected Platforms:</strong> ' + 
                (platforms.length > 0 ? platforms.join(', ') : 'None') + '</p>' +
                '<p><strong>Auto-Connect:</strong> ' + 
                (document.getElementById('wizard_auto_connect').checked ? 'Yes' : 'No') + '</p>';
            
            document.getElementById('review-platform-config').innerHTML = platformConfigHtml;
            
            // Content Preferences
            var contentTypes = [];
            var contentTypeOptions = document.getElementById('wizard_content_types').selectedOptions;
            for (var i = 0; i < contentTypeOptions.length; i++) {
                contentTypes.push(contentTypeOptions[i].text);
            }
            
            var postingFrequency = document.getElementById('wizard_posting_frequency').options[
                document.getElementById('wizard_posting_frequency').selectedIndex
            ].text;
            
            var contentSources = [];
            var contentSourceOptions = document.getElementById('wizard_content_sources').selectedOptions;
            for (var j = 0; j < contentSourceOptions.length; j++) {
                contentSources.push(contentSourceOptions[j].text);
            }
            
            var timezone = document.getElementById('wizard_timezone').options[
                document.getElementById('wizard_timezone').selectedIndex
            ].text;
            
            var dateFormat = document.getElementById('wizard_date_format').options[
                document.getElementById('wizard_date_format').selectedIndex
            ].text;
            
            var enableAnalytics = document.getElementById('wizard_enable_analytics').checked;
            
            var contentPrefsHtml = '<p><strong>Content Types:</strong> ' + contentTypes.join(', ') + '</p>' +
                                  '<p><strong>Posting Frequency:</strong> ' + postingFrequency + '</p>' +
                                  '<p><strong>Content Sources:</strong> ' + contentSources.join(', ') + '</p>' +
                                  '<p><strong>Timezone:</strong> ' + timezone + '</p>' +
                                  '<p><strong>Date Format:</strong> ' + dateFormat + '</p>' +
                                  '<p><strong>Analytics:</strong> ' + (enableAnalytics ? 'Enabled' : 'Disabled') + '</p>';
            
            document.getElementById('review-content-prefs').innerHTML = contentPrefsHtml;
            
            // Advanced Options
            var approvalWorkflow = document.getElementById('wizard_enable_approval_workflow').checked;
            var teamCollaboration = document.getElementById('wizard_enable_team_collaboration').checked;
            var dataRetention = document.getElementById('wizard_data_retention').options[
                document.getElementById('wizard_data_retention').selectedIndex
            ].text;
            var logLevel = document.getElementById('wizard_log_level').options[
                document.getElementById('wizard_log_level').selectedIndex
            ].text;
            var enableBackups = document.getElementById('wizard_enable_backups').checked;
            var enableNotifications = document.getElementById('wizard_enable_notifications').checked;
            
            var advancedOptsHtml = '<p><strong>Approval Workflow:</strong> ' + (approvalWorkflow ? 'Enabled' : 'Disabled') + '</p>' +
                                  '<p><strong>Team Collaboration:</strong> ' + (teamCollaboration ? 'Enabled' : 'Disabled') + '</p>' +
                                  '<p><strong>Data Retention:</strong> ' + dataRetention + '</p>' +
                                  '<p><strong>Log Level:</strong> ' + logLevel + '</p>' +
                                  '<p><strong>Automatic Backups:</strong> ' + (enableBackups ? 'Enabled' : 'Disabled') + '</p>' +
                                  '<p><strong>Email Notifications:</strong> ' + (enableNotifications ? 'Enabled' : 'Disabled') + '</p>';
            
            document.getElementById('review-advanced-opts').innerHTML = advancedOptsHtml;
        }
        
        function smoCompleteWizard() {
            // Collect all wizard data
            var wizardData = {
                user_info: {
                    name: document.getElementById('wizard_user_name').value,
                    email: document.getElementById('wizard_user_email').value,
                    role: document.getElementById('wizard_user_role').value,
                    business_type: document.getElementById('wizard_business_type').value,
                    primary_goal: document.getElementById('wizard_primary_goal').value,
                    experience_level: document.getElementById('wizard_experience_level').value
                },
                ai_config: {
                    enabled: document.getElementById('wizard_ai_enabled').checked,
                    provider: document.getElementById('wizard_ai_provider').value,
                    tone: document.getElementById('wizard_ai_tone').value,
                    variants: document.getElementById('wizard_ai_variants').checked,
                    auto_hashtags: document.getElementById('wizard_auto_hashtags').checked,
                    auto_scheduling: document.getElementById('wizard_auto_scheduling').checked
                },
                platform_config: {
                    platforms: Array.from(document.querySelectorAll('#wizard-step-3 input[type="checkbox"]:checked')).map(function(el) {
                        return el.value;
                    }),
                    auto_connect: document.getElementById('wizard_auto_connect').checked
                },
                content_prefs: {
                    content_types: Array.from(document.getElementById('wizard_content_types').selectedOptions).map(function(el) {
                        return el.value;
                    }),
                    posting_frequency: document.getElementById('wizard_posting_frequency').value,
                    content_sources: Array.from(document.getElementById('wizard_content_sources').selectedOptions).map(function(el) {
                        return el.value;
                    }),
                    timezone: document.getElementById('wizard_timezone').value,
                    date_format: document.getElementById('wizard_date_format').value,
                    enable_analytics: document.getElementById('wizard_enable_analytics').checked
                },
                advanced_opts: {
                    enable_approval_workflow: document.getElementById('wizard_enable_approval_workflow').checked,
                    enable_team_collaboration: document.getElementById('wizard_enable_team_collaboration').checked,
                    data_retention: document.getElementById('wizard_data_retention').value,
                    log_level: document.getElementById('wizard_log_level').value,
                    enable_backups: document.getElementById('wizard_enable_backups').checked,
                    enable_notifications: document.getElementById('wizard_enable_notifications').checked
                }
            };
            
            // Send data to server via AJAX
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Show completion message
                                document.querySelectorAll('.smo-wizard-step').forEach(function(step) {
                                    step.style.display = 'none';
                                });
                                document.getElementById('wizard-completion').style.display = 'block';
                                
                                // Scroll to top
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            } else {
                                alert('Error: ' + (response.data.message || 'Failed to save configuration'));
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('An error occurred while processing your request.');
                        }
                    } else {
                        alert('An error occurred while saving your configuration.');
                    }
                }
            };
            
            // Send the request
            var params = 'action=smo_save_wizard_config&' +
                        'nonce=' + encodeURIComponent('<?php echo wp_create_nonce("smo_wizard_nonce"); ?>') + '&' +
                        'config=' + encodeURIComponent(JSON.stringify(wizardData));
            
            xhr.send(params);
        }
        
        // Initialize wizard
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial progress
            document.getElementById('smo-wizard-progress').style.width = '0%';
            
            // Toggle AI fields based on AI enabled checkbox
            var aiEnabledCheckbox = document.getElementById('wizard_ai_enabled');
            if (aiEnabledCheckbox) {
                aiEnabledCheckbox.addEventListener('change', function() {
                    var aiFields = document.querySelectorAll('#wizard_ai_provider_field, #wizard_ai_tone_field');
                    aiFields.forEach(function(field) {
                        if (this.checked) {
                            field.style.display = 'block';
                        } else {
                            field.style.display = 'none';
                        }
                    }.bind(this));
                });
                
                // Trigger change event to set initial state
                aiEnabledCheckbox.dispatchEvent(new Event('change'));
            }

            // Track wizard start event
            smoTrackWizardEvent('wizard_started');
            
            // Track time spent on each step
            var stepStartTime = Date.now();
            var currentStep = 1;
            
            // Override next step function to add tracking
            var originalSmoWizardNextStep = window.smoWizardNextStep;
            window.smoWizardNextStep = function(stepNumber) {
                // Track step completion
                var timeSpent = Date.now() - stepStartTime;
                smoTrackWizardEvent('step_completed', {
                    step: currentStep,
                    time_spent: timeSpent
                });
                
                // Call original next step function
                originalSmoWizardNextStep(stepNumber);
                
                // Update tracking variables
                stepStartTime = Date.now();
                currentStep = stepNumber;
                
                // Track step view
                smoTrackWizardEvent('step_viewed', {
                    step: stepNumber
                });
            };
            
            // Override completion function to add tracking
            var originalSmoCompleteWizard = window.smoCompleteWizard;
            window.smoCompleteWizard = function() {
                var timeSpent = Date.now() - stepStartTime;
                smoTrackWizardEvent('step_completed', {
                    step: currentStep,
                    time_spent: timeSpent
                });
                
                // Track total completion time
                var totalTime = Date.now() - window.wizardStartTime;
                smoTrackWizardEvent('wizard_completed', {
                    total_time: totalTime,
                    final_step: currentStep
                });
                
                // Call original completion function
                originalSmoCompleteWizard();
            };
            
            // Initialize start time tracking
            window.wizardStartTime = Date.now();
        });
        
        // Analytics tracking function
        function smoTrackWizardEvent(eventType, data) {
            var trackingData = {
                action: 'smo_track_wizard_analytics',
                nonce: '<?php echo wp_create_nonce("smo_wizard_analytics_nonce"); ?>',
                event_type: eventType,
                step: data.step || null,
                time_spent: data.time_spent || null,
                total_time: data.total_time || null,
                final_step: data.final_step || null,
                timestamp: new Date().toISOString(),
                user_agent: navigator.userAgent,
                screen_resolution: screen.width + 'x' + screen.height
            };
            
            // Send analytics data
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    // Silently handle analytics - don't interfere with wizard flow
                    console.log('Wizard analytics tracked:', eventType);
                }
            };
            
            var params = Object.keys(trackingData)
                .map(function(key) {
                    return encodeURIComponent(key) + '=' + encodeURIComponent(trackingData[key]);
                })
                .join('&');
            
            xhr.send(params);
        }
        
        // Track form field interactions
        document.addEventListener('change', function(e) {
            if (e.target.matches('[id^="wizard_"]')) {
                smoTrackWizardEvent('field_interaction', {
                    field_id: e.target.id,
                    field_type: e.target.type,
                    field_value: e.target.value
                });
            }
        });
        
        // Track button clicks
        document.addEventListener('click', function(e) {
            if (e.target.matches('.smo-btn')) {
                smoTrackWizardEvent('button_click', {
                    button_text: e.target.textContent.trim(),
                    button_step: e.target.closest('.smo-wizard-step')?.id || 'unknown'
                });
            }
        });
        </script>
        
        <!-- Wizard CSS -->
        <style>
        .smo-magic-wizard-wrapper {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .smo-wizard-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .smo-wizard-title {
            color: #2271b1;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .smo-wizard-title .dashicons {
            font-size: 32px;
            vertical-align: middle;
            margin-right: 10px;
        }
        
        .smo-wizard-subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .smo-wizard-progress {
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .smo-progress-bar {
            height: 100%;
            background: #2271b1;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .smo-wizard-step {
            display: none;
        }
        
        .smo-wizard-step.active {
            display: block;
        }
        
        .smo-step-header {
            margin-bottom: 25px;
        }
        
        .smo-step-header h3 {
            color: #2271b1;
            font-size: 22px;
            margin-bottom: 10px;
        }
        
        .smo-step-header p {
            color: #666;
            font-size: 14px;
        }
        
        .smo-form-grid {
            display: grid;
            gap: 20px;
        }
        
        .smo-form-grid.two-columns {
            grid-template-columns: 1fr 1fr;
        }
        
        .smo-form-field {
            margin-bottom: 20px;
        }
        
        .smo-form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .smo-input, .smo-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .smo-input:focus, .smo-select:focus {
            outline: none;
            border-color: #2271b1;
            box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.2);
        }
        
        .smo-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .smo-toggle-input {
            margin: 0;
        }
        
        .smo-toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            background: #ddd;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .smo-toggle-input:checked + .smo-toggle-switch {
            background: #2271b1;
        }
        
        .smo-toggle-switch::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s;
        }
        
        .smo-toggle-input:checked + .smo-toggle-switch::after {
            transform: translateX(26px);
        }
        
        .smo-toggle-label {
            font-size: 14px;
            color: #666;
        }
        
        .smo-platform-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .smo-platform-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .smo-platform-card:hover {
            border-color: #2271b1;
            box-shadow: 0 2px 8px rgba(34, 113, 177, 0.1);
        }
        
        .smo-platform-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .smo-platform-icon {
            font-size: 24px;
            color: #2271b1;
        }
        
        .smo-platform-name {
            font-size: 14px;
            font-weight: 500;
        }
        
        .smo-platform-checkbox {
            position: absolute;
            opacity: 0;
        }
        
        .smo-platform-label input[type="checkbox"]:checked + .smo-platform-icon {
            color: #fff;
            background: #2271b1;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .smo-review-summary {
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .smo-review-section {
            margin-bottom: 20px;
        }
        
        .smo-review-section h4 {
            color: #2271b1;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .smo-review-content {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .smo-review-content p {
            margin: 5px 0;
        }
        
        .smo-wizard-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .smo-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .smo-btn-primary {
            background: #2271b1;
            color: white;
        }
        
        .smo-btn-primary:hover {
            background: #19588c;
        }
        
        .smo-btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .smo-btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .smo-btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .smo-btn-success:hover {
            background: #3e8e41;
        }
        
        .smo-wizard-completion {
            text-align: center;
            padding: 40px 20px;
        }
        
        .smo-completion-icon {
            font-size: 60px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        
        .smo-completion-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .smo-form-grid.two-columns {
                grid-template-columns: 1fr;
            }
            
            .smo-wizard-actions {
                flex-direction: column;
            }
            
            .smo-btn {
                width: 100%;
                justify-content: center;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Get current settings for pre-filling the wizard
     */
    private function get_current_settings()
    {
        // Get existing settings or use defaults
        $defaults = array(
            'user_role' => 'business_owner',
            'business_type' => 'ecommerce',
            'primary_goal' => 'brand_awareness',
            'experience_level' => 'intermediate',
            'ai_enabled' => 1,
            'ai_provider' => 'huggingface',
            'ai_tone' => 'professional',
            'ai_variants' => 0,
            'auto_hashtags' => 1,
            'auto_scheduling' => 1,
            'platforms' => array('twitter', 'facebook', 'linkedin'),
            'auto_connect' => 0,
            'content_types' => array('text_posts', 'images', 'links'),
            'posting_frequency' => 'weekly',
            'content_sources' => array('original', 'curated'),
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d H:i:s',
            'enable_analytics' => 1,
            'enable_approval_workflow' => 0,
            'enable_team_collaboration' => 0,
            'data_retention' => '365',
            'log_level' => 'info',
            'enable_backups' => 1,
            'enable_notifications' => 1
        );
        
        $settings = array();
        foreach ($defaults as $key => $default) {
            $settings[$key] = get_option('smo_social_wizard_' . $key, $default);
        }
        
        return $settings;
    }
    
    /**
     * Get available AI providers
     */
    private function get_available_ai_providers()
    {
        $providers = array(
            'huggingface' => 'HuggingFace (Free)',
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'openrouter' => 'OpenRouter',
            'localhost' => 'Localhost AI (Ollama, LM Studio)',
            'custom' => 'Custom AI API'
        );

        // Check if ProvidersConfig exists for dynamic providers
        if (class_exists('\SMO_Social\AI\ProvidersConfig')) {
            $dynamic_providers = \SMO_Social\AI\ProvidersConfig::get_all_providers();
            foreach ($dynamic_providers as $id => $provider) {
                $providers[$id] = $provider['name'];
            }
        }

        return $providers;
    }
    
    /**
     * Get available social platforms
     */
    private function get_available_social_platforms()
    {
        $platforms = array(
            'twitter' => 'Twitter',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'pinterest' => 'Pinterest',
            'reddit' => 'Reddit',
            'threads' => 'Threads',
            'mastodon' => 'Mastodon'
        );
        
        // Check if PlatformManager exists for dynamic platforms
        if (class_exists('\SMO_Social\Platforms\Manager')) {
            $platform_manager = new \SMO_Social\Platforms\Manager();
            $available_platforms = $platform_manager->get_available_platforms();
            foreach ($available_platforms as $platform) {
                $platforms[$platform->get_slug()] = $platform->get_name();
            }
        }
        
        return $platforms;
    }
    
    /**
     * Get platform icon
     */
    private function get_platform_icon($platform_id)
    {
        $icons = array(
            'twitter' => '🐦',
            'facebook' => '📘',
            'instagram' => '📷',
            'linkedin' => '💼',
            'youtube' => '📹',
            'tiktok' => '🎵',
            'pinterest' => '📌',
            'reddit' => '👽',
            'threads' => '🧵',
            'mastodon' => '🐘'
        );
        
        return isset($icons[$platform_id]) ? $icons[$platform_id] : '🌐';
    }
}