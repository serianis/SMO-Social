<?php
/**
 * Content Organizer View - Enhanced
 * Unified interface for content organization, import, categories, and ideas
 * Features: Modern UI, Gradient Design, Drag & Drop, Smart Filtering
 * 
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../../Content/ContentCategoriesManager.php';
require_once __DIR__ . '/../../Content/ContentIdeasManager.php';
require_once __DIR__ . '/../../Content/ContentImportManager.php';

/**
 * Content Organizer Class
 */
class ContentOrganizer {
    
    private $categories_manager;
    private $ideas_manager;
    private $import_manager;
    
    public function __construct() {
        $this->categories_manager = new \SMO_Social\Content\ContentCategoriesManager();
        $this->ideas_manager = new \SMO_Social\Content\ContentIdeasManager();
        $this->import_manager = new \SMO_Social\Content\ContentImportManager();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'smo-social') === false) {
            return;
        }
        
        wp_enqueue_style(
            'smo-content-organizer',
            SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-content-organizer.css',
            array('smo-social-admin'),
            SMO_SOCIAL_VERSION
        );
        
        wp_enqueue_script(
            'smo-content-organizer',
            SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-content-organizer.js',
            array('jquery', 'wp-util', 'smo-social-admin', 'jquery-ui-sortable'),
            SMO_SOCIAL_VERSION,
            true
        );
        
        wp_localize_script('smo-content-organizer', 'smoContentOrganizer', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_social_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'smo-social'),
                'success' => __('Success!', 'smo-social'),
                'error' => __('Error occurred', 'smo-social'),
                'confirmDelete' => __('Are you sure you want to delete this?', 'smo-social'),
                'noCategories' => __('No categories found. Create your first category!', 'smo-social'),
                'noIdeas' => __('No ideas yet. Capture your first idea!', 'smo-social'),
                'noImports' => __('No imported content. Connect your first source!', 'smo-social'),
                'saved' => __('Saved successfully!', 'smo-social'),
                'deleted' => __('Deleted successfully!', 'smo-social')
            )
        ));
    }
    
    /**
     * Render the Content Organizer page
     */
    public function render() {
        // Use Common Layout with AppLayout helpers
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_start('content-organizer', __('Content Organizer', 'smo-social'));
            
            // Render standardized gradient header using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_header([
                'icon' => '📚',
                'title' => __('Content Organizer', 'smo-social'),
                'subtitle' => __('Your central hub for organizing, categorizing, and managing all your content', 'smo-social'),
                'actions' => [
                    [
                        'id' => 'smo-quick-add-idea',
                        'label' => __('Quick Idea', 'smo-social'),
                        'icon' => 'lightbulb',
                        'class' => 'smo-btn-primary'
                    ],
                    [
                        'id' => 'smo-add-category',
                        'label' => __('New Category', 'smo-social'),
                        'icon' => 'category',
                        'class' => 'smo-btn-secondary'
                    ]
                ]
            ]);
            
            // Render standardized stats dashboard using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
                [
                    'icon' => 'category',
                    'value' => '-',
                    'label' => __('Categories', 'smo-social'),
                    'trend' => '📁 Organized',
                    'id' => 'total-categories'
                ],
                [
                    'icon' => 'lightbulb',
                    'value' => '-',
                    'label' => __('Content Ideas', 'smo-social'),
                    'trend' => '✨ Captured',
                    'id' => 'total-ideas'
                ],
                [
                    'icon' => 'admin-page',
                    'value' => '-',
                    'label' => __('Drafts', 'smo-social'),
                    'trend' => '📝 In progress',
                    'id' => 'total-drafts'
                ],
                [
                    'icon' => 'calendar-alt',
                    'value' => '-',
                    'label' => __('Scheduled', 'smo-social'),
                    'trend' => '⏰ Ready to post',
                    'id' => 'total-scheduled'
                ]
            ]);
        }
        ?>
            
            <!-- Quick Actions Bar -->
            <div class="smo-quick-actions">
                <button class="smo-quick-action-btn" id="smo-bulk-organize">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <span><?php esc_html_e('Bulk Organize', 'smo-social'); ?></span>
                </button>
                <button class="smo-quick-action-btn" id="smo-export-content">
                    <span class="dashicons dashicons-download"></span>
                    <span><?php esc_html_e('Export Content', 'smo-social'); ?></span>
                </button>
                <button class="smo-quick-action-btn" id="smo-import-content">
                    <span class="dashicons dashicons-upload"></span>
                    <span><?php esc_html_e('Import Content', 'smo-social'); ?></span>
                </button>
                <button class="smo-quick-action-btn" id="smo-view-calendar">
                    <span class="dashicons dashicons-calendar"></span>
                    <span><?php esc_html_e('Content Calendar', 'smo-social'); ?></span>
                </button>
            </div>
            
            <!-- Main Content Tabs -->
            <div class="smo-organizer-tabs">
                <nav class="smo-tabs-nav">
                    <a href="#categories" class="smo-tab-link active" data-tab="categories">
                        <span class="dashicons dashicons-category"></span>
                        <?php esc_html_e('Categories', 'smo-social'); ?>
                        <span class="smo-tab-badge" id="categories-badge">0</span>
                    </a>
                    <a href="#ideas" class="smo-tab-link" data-tab="ideas">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php esc_html_e('Ideas', 'smo-social'); ?>
                        <span class="smo-tab-badge" id="ideas-badge">0</span>
                    </a>
                    <a href="#content-library" class="smo-tab-link" data-tab="content-library">
                        <span class="dashicons dashicons-admin-media"></span>
                        <?php esc_html_e('Content Library', 'smo-social'); ?>
                    </a>
                    <a href="#templates" class="smo-tab-link" data-tab="templates">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php esc_html_e('Templates', 'smo-social'); ?>
                    </a>
                </nav>
                
                <div class="smo-tabs-content">
                    <!-- Categories Tab -->
                    <div class="smo-tab-panel active" id="categories-panel">
                        <?php $this->render_categories_panel(); ?>
                    </div>
                    
                    <!-- Ideas Tab -->
                    <div class="smo-tab-panel" id="ideas-panel">
                        <?php $this->render_ideas_panel(); ?>
                    </div>
                    
                    <!-- Content Library Tab -->
                    <div class="smo-tab-panel" id="content-library-panel">
                        <?php $this->render_content_library_panel(); ?>
                    </div>
                    
                    <!-- Templates Tab -->
                    <div class="smo-tab-panel" id="templates-panel">
                        <?php $this->render_templates_panel(); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        $this->render_modals();
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_end();
        }
    }
    
    /**
     * Render Categories Panel
     */
    private function render_categories_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Content Categories', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Organize your content into categories for better management and planning', 'smo-social'); ?></p>
            </div>
            <div class="smo-panel-actions">
                <div class="smo-view-toggle">
                    <button class="smo-view-btn active" data-view="grid" title="<?php esc_attr_e('Grid View', 'smo-social'); ?>">
                        <span class="dashicons dashicons-grid-view"></span>
                    </button>
                    <button class="smo-view-btn" data-view="list" title="<?php esc_attr_e('List View', 'smo-social'); ?>">
                        <span class="dashicons dashicons-list-view"></span>
                    </button>
                </div>
                <button class="smo-btn smo-btn-primary" id="smo-create-category">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Create Category', 'smo-social'); ?>
                </button>
            </div>
        </div>
        
        <div class="smo-categories-container">
            <div class="smo-categories-grid" id="categories-container">
                <div class="smo-loading">
                    <div class="smo-spinner"></div>
                    <p><?php esc_html_e('Loading categories...', 'smo-social'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Ideas Panel
     */
    private function render_ideas_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Content Ideas', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Capture, organize, and develop your content ideas into engaging posts', 'smo-social'); ?></p>
            </div>
            <div class="smo-panel-actions">
                <div class="smo-filter-group">
                    <select id="ideas-filter-status" aria-label="<?php esc_attr_e('Filter Ideas by Status', 'smo-social'); ?>">
                        <option value=""><?php esc_html_e('All Statuses', 'smo-social'); ?></option>
                        <option value="idea"><?php esc_html_e('Ideas', 'smo-social'); ?></option>
                        <option value="draft"><?php esc_html_e('Drafts', 'smo-social'); ?></option>
                        <option value="scheduled"><?php esc_html_e('Scheduled', 'smo-social'); ?></option>
                        <option value="published"><?php esc_html_e('Published', 'smo-social'); ?></option>
                    </select>
                    <select id="ideas-filter-category" aria-label="<?php esc_attr_e('Filter Ideas by Category', 'smo-social'); ?>">
                        <option value=""><?php esc_html_e('All Categories', 'smo-social'); ?></option>
                    </select>
                    <select id="ideas-filter-priority" aria-label="<?php esc_attr_e('Filter Ideas by Priority', 'smo-social'); ?>">
                        <option value=""><?php esc_html_e('All Priorities', 'smo-social'); ?></option>
                        <option value="high"><?php esc_html_e('High Priority', 'smo-social'); ?></option>
                        <option value="medium"><?php esc_html_e('Medium Priority', 'smo-social'); ?></option>
                        <option value="low"><?php esc_html_e('Low Priority', 'smo-social'); ?></option>
                    </select>
                </div>
                <button class="smo-btn smo-btn-primary" id="smo-add-idea">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Add Idea', 'smo-social'); ?>
                </button>
            </div>
        </div>
        
        <div class="smo-ideas-container">
            <!-- Kanban Board View -->
            <div class="smo-ideas-kanban" id="ideas-kanban">
                <div class="smo-kanban-column" data-status="idea">
                    <div class="smo-kanban-header">
                        <h3>💡 <?php esc_html_e('Ideas', 'smo-social'); ?></h3>
                        <span class="smo-kanban-count">0</span>
                    </div>
                    <div class="smo-kanban-items" id="kanban-ideas">
                        <div class="smo-loading">
                            <div class="smo-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <div class="smo-kanban-column" data-status="draft">
                    <div class="smo-kanban-header">
                        <h3>📝 <?php esc_html_e('Drafts', 'smo-social'); ?></h3>
                        <span class="smo-kanban-count">0</span>
                    </div>
                    <div class="smo-kanban-items" id="kanban-drafts">
                        <div class="smo-loading">
                            <div class="smo-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <div class="smo-kanban-column" data-status="scheduled">
                    <div class="smo-kanban-header">
                        <h3>⏰ <?php esc_html_e('Scheduled', 'smo-social'); ?></h3>
                        <span class="smo-kanban-count">0</span>
                    </div>
                    <div class="smo-kanban-items" id="kanban-scheduled">
                        <div class="smo-loading">
                            <div class="smo-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <div class="smo-kanban-column" data-status="published">
                    <div class="smo-kanban-header">
                        <h3>✅ <?php esc_html_e('Published', 'smo-social'); ?></h3>
                        <span class="smo-kanban-count">0</span>
                    </div>
                    <div class="smo-kanban-items" id="kanban-published">
                        <div class="smo-loading">
                            <div class="smo-spinner"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Content Library Panel
     */
    private function render_content_library_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Content Library', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Browse and manage all your content assets in one place', 'smo-social'); ?></p>
            </div>
            <div class="smo-panel-actions">
                <input type="search" id="library-search" class="smo-search-input" placeholder="<?php esc_attr_e('Search content...', 'smo-social'); ?>">
                <select id="library-filter-type">
                    <option value=""><?php esc_html_e('All Types', 'smo-social'); ?></option>
                    <option value="image"><?php esc_html_e('Images', 'smo-social'); ?></option>
                    <option value="video"><?php esc_html_e('Videos', 'smo-social'); ?></option>
                    <option value="text"><?php esc_html_e('Text Posts', 'smo-social'); ?></option>
                    <option value="link"><?php esc_html_e('Links', 'smo-social'); ?></option>
                </select>
                <button class="smo-btn smo-btn-primary" id="smo-upload-content">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Upload', 'smo-social'); ?>
                </button>
            </div>
        </div>
        
        <div id="content-library-grid" class="smo-content-library-grid">
            <div class="smo-loading">
                <div class="smo-spinner"></div>
                <p><?php esc_html_e('Loading content library...', 'smo-social'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Templates Panel
     */
    private function render_templates_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Content Templates', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Save time with pre-designed templates for your social media posts', 'smo-social'); ?></p>
            </div>
            <div class="smo-panel-actions">
                <select id="template-filter-category">
                    <option value=""><?php esc_html_e('All Categories', 'smo-social'); ?></option>
                    <option value="promotional"><?php esc_html_e('Promotional', 'smo-social'); ?></option>
                    <option value="educational"><?php esc_html_e('Educational', 'smo-social'); ?></option>
                    <option value="engagement"><?php esc_html_e('Engagement', 'smo-social'); ?></option>
                    <option value="announcement"><?php esc_html_e('Announcement', 'smo-social'); ?></option>
                </select>
                <button class="smo-btn smo-btn-primary" id="smo-create-template">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Create Template', 'smo-social'); ?>
                </button>
            </div>
        </div>
        
        <div id="templates-grid" class="smo-templates-grid">
            <div class="smo-loading">
                <div class="smo-spinner"></div>
                <p><?php esc_html_e('Loading templates...', 'smo-social'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render modals
     */
    private function render_modals() {
        ?>
        <!-- Category Modal -->
        <div id="smo-category-modal" class="smo-modal">
            <div class="smo-modal-content smo-modal-modern">
                <span class="smo-modal-close">&times;</span>
                <div class="smo-modal-header">
                    <h3 id="smo-category-modal-title"><?php esc_html_e('Create Category', 'smo-social'); ?></h3>
                </div>
                <div class="smo-modal-body">
                    <form id="smo-category-form">
                        <input type="hidden" id="category-id" value="">
                        
                        <div class="smo-form-group">
                            <label for="category-name"><?php esc_html_e('Category Name *', 'smo-social'); ?></label>
                            <input type="text" id="category-name" required placeholder="<?php esc_attr_e('e.g., Product Launches', 'smo-social'); ?>">
                        </div>
                        
                        <div class="smo-form-group">
                            <label for="category-description"><?php esc_html_e('Description', 'smo-social'); ?></label>
                            <textarea id="category-description" rows="3" placeholder="<?php esc_attr_e('Describe this category...', 'smo-social'); ?>"></textarea>
                        </div>
                        
                        <div class="smo-form-row">
                            <div class="smo-form-group">
                                <label for="category-color"><?php esc_html_e('Color', 'smo-social'); ?></label>
                                <input type="color" id="category-color" value="#667eea">
                            </div>
                            
                            <div class="smo-form-group">
                                <label for="category-icon"><?php esc_html_e('Icon', 'smo-social'); ?></label>
                                <select id="category-icon">
                                    <option value="📁">📁 Folder</option>
                                    <option value="⭐">⭐ Star</option>
                                    <option value="❤️">❤️ Heart</option>
                                    <option value="📢">📢 Megaphone</option>
                                    <option value="📈">📈 Chart</option>
                                    <option value="💡">💡 Lightbulb</option>
                                    <option value="🎯">🎯 Target</option>
                                    <option value="🚀">🚀 Rocket</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="smo-form-actions">
                            <button type="submit" class="smo-btn smo-btn-primary">
                                <?php esc_html_e('Save Category', 'smo-social'); ?>
                            </button>
                            <button type="button" class="smo-btn smo-btn-secondary smo-cancel-modal">
                                <?php esc_html_e('Cancel', 'smo-social'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Quick Idea Modal -->
        <div id="smo-quick-idea-modal" class="smo-modal">
            <div class="smo-modal-content smo-modal-modern">
                <span class="smo-modal-close">&times;</span>
                <div class="smo-modal-header">
                    <h3><?php esc_html_e('Quick Idea Capture', 'smo-social'); ?></h3>
                </div>
                <div class="smo-modal-body">
                    <form id="smo-quick-idea-form">
                        <div class="smo-form-group">
                            <label for="quick-idea-title"><?php esc_html_e('Idea Title *', 'smo-social'); ?></label>
                            <input type="text" id="quick-idea-title" required placeholder="<?php esc_attr_e('What\'s your idea?', 'smo-social'); ?>">
                        </div>
                        
                        <div class="smo-form-group">
                            <label for="quick-idea-description"><?php esc_html_e('Quick Notes', 'smo-social'); ?></label>
                            <textarea id="quick-idea-description" rows="4" placeholder="<?php esc_attr_e('Jot down your thoughts...', 'smo-social'); ?>"></textarea>
                        </div>
                        
                        <div class="smo-form-row">
                            <div class="smo-form-group">
                                <label for="quick-idea-category"><?php esc_html_e('Category', 'smo-social'); ?></label>
                                <select id="quick-idea-category">
                                    <option value=""><?php esc_html_e('Select category...', 'smo-social'); ?></option>
                                </select>
                            </div>
                            
                            <div class="smo-form-group">
                                <label for="quick-idea-priority"><?php esc_html_e('Priority', 'smo-social'); ?></label>
                                <select id="quick-idea-priority">
                                    <option value="low"><?php esc_html_e('Low', 'smo-social'); ?></option>
                                    <option value="medium" selected><?php esc_html_e('Medium', 'smo-social'); ?></option>
                                    <option value="high"><?php esc_html_e('High', 'smo-social'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="smo-form-actions">
                            <button type="submit" class="smo-btn smo-btn-primary">
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e('Capture Idea', 'smo-social'); ?>
                            </button>
                            <button type="button" class="smo-btn smo-btn-secondary smo-cancel-modal">
                                <?php esc_html_e('Cancel', 'smo-social'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Idea Detail Modal -->
        <div id="smo-idea-detail-modal" class="smo-modal">
            <div class="smo-modal-content smo-modal-modern smo-modal-large">
                <span class="smo-modal-close">&times;</span>
                <div class="smo-modal-header">
                    <h3 id="idea-detail-title"><?php esc_html_e('Idea Details', 'smo-social'); ?></h3>
                </div>
                <div class="smo-modal-body">
                    <form id="smo-idea-detail-form">
                        <input type="hidden" id="idea-id" value="">
                        
                        <div class="smo-form-group">
                            <label for="idea-title"><?php esc_html_e('Title *', 'smo-social'); ?></label>
                            <input type="text" id="idea-title" required>
                        </div>
                        
                        <div class="smo-form-group">
                            <label for="idea-content"><?php esc_html_e('Content', 'smo-social'); ?></label>
                            <textarea id="idea-content" rows="6"></textarea>
                        </div>
                        
                        <div class="smo-form-row">
                            <div class="smo-form-group">
                                <label for="idea-category"><?php esc_html_e('Category', 'smo-social'); ?></label>
                                <select id="idea-category"></select>
                            </div>
                            
                            <div class="smo-form-group">
                                <label for="idea-priority"><?php esc_html_e('Priority', 'smo-social'); ?></label>
                                <select id="idea-priority">
                                    <option value="low"><?php esc_html_e('Low', 'smo-social'); ?></option>
                                    <option value="medium"><?php esc_html_e('Medium', 'smo-social'); ?></option>
                                    <option value="high"><?php esc_html_e('High', 'smo-social'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="smo-form-row">
                            <div class="smo-form-group">
                                <label for="idea-status"><?php esc_html_e('Status', 'smo-social'); ?></label>
                                <select id="idea-status">
                                    <option value="idea"><?php esc_html_e('Idea', 'smo-social'); ?></option>
                                    <option value="draft"><?php esc_html_e('Draft', 'smo-social'); ?></option>
                                    <option value="scheduled"><?php esc_html_e('Scheduled', 'smo-social'); ?></option>
                                    <option value="published"><?php esc_html_e('Published', 'smo-social'); ?></option>
                                </select>
                            </div>
                            
                            <div class="smo-form-group">
                                <label for="idea-scheduled-date"><?php esc_html_e('Scheduled Date', 'smo-social'); ?></label>
                                <input type="datetime-local" id="idea-scheduled-date">
                            </div>
                        </div>
                        
                        <div class="smo-form-group">
                            <label for="idea-tags"><?php esc_html_e('Tags', 'smo-social'); ?></label>
                            <input type="text" id="idea-tags" placeholder="<?php esc_attr_e('Separate tags with commas', 'smo-social'); ?>">
                        </div>
                        
                        <div class="smo-form-actions">
                            <button type="submit" class="smo-btn smo-btn-primary">
                                <?php esc_html_e('Save Idea', 'smo-social'); ?>
                            </button>
                            <button type="button" class="smo-btn smo-btn-outline" id="convert-to-post">
                                <span class="dashicons dashicons-share"></span>
                                <?php esc_html_e('Convert to Post', 'smo-social'); ?>
                            </button>
                            <button type="button" class="smo-btn smo-btn-secondary smo-cancel-modal">
                                <?php esc_html_e('Cancel', 'smo-social'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
