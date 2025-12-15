<?php
/**
 * SMO Social Templates View
 * 
 * Content templates management with modern gradient design
 * 
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load Post Templates Manager
if (file_exists(dirname(__DIR__, 2) . '/Features/PostTemplatesManager.php')) {
    require_once dirname(__DIR__, 2) . '/Features/PostTemplatesManager.php';
}

// Get templates from database
$templates = array();
if (class_exists('\SMO_Social\Features\PostTemplatesManager')) {
    $templates = \SMO_Social\Features\PostTemplatesManager::get_user_templates();
    $categories = \SMO_Social\Features\PostTemplatesManager::get_categories();
} else {
    // Fallback to sample templates
    $templates = array(
        array(
            'id' => 1,
            'name' => 'Product Launch Campaign',
            'description' => 'Complete template for launching a new product',
            'platforms' => array('twitter', 'facebook', 'linkedin'),
            'content' => "🚀 Exciting news! We're launching our new product {PRODUCT_NAME}!\n\n{KEY_FEATURES}\n\n#ProductLaunch #Innovation",
            'category' => 'product_launch',
            'usage_count' => 15
        ),
        array(
            'id' => 2,
            'name' => 'Webinar Promotion',
            'description' => 'Promote your upcoming webinar',
            'platforms' => array('twitter', 'facebook', 'linkedin'),
            'content' => "📺 Join us for an exclusive webinar!\n\nTopic: {TOPIC}\nDate: {DATE}\nTime: {TIME}\n\nRegister now: {REGISTER_LINK}\n\n#Webinar #Education #Learning",
            'category' => 'event',
            'usage_count' => 8
        ),
        array(
            'id' => 3,
            'name' => 'Behind the Scenes',
            'description' => 'Show your company culture and process',
            'platforms' => array('instagram', 'twitter', 'facebook'),
            'content' => "👀 Behind the scenes at {COMPANY_NAME}!\n\n{DESCRIPTION}\n\n#BehindTheScenes #CompanyCulture #TeamWork",
            'category' => 'behind_scenes',
            'usage_count' => 12
        )
    );
    $categories = array(
        'general' => 'General',
        'product_launch' => 'Product Launch',
        'event' => 'Event',
        'behind_scenes' => 'Behind the Scenes'
    );
}

// Calculate stats
$total_templates = count($templates);
$total_usage = array_sum(array_column($templates, 'usage_count'));
$avg_usage = $total_templates > 0 ? round($total_usage / $total_templates, 1) : 0;
$total_categories = count($categories);

// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('templates', __('Content Templates', 'smo-social'));
}
?>

<!-- Modern Gradient Header -->
<div class="smo-import-header">
    <div class="smo-header-content">
        <h1 class="smo-page-title">
            <span class="smo-icon">📋</span>
            <?php _e('Content Templates', 'smo-social'); ?>
        </h1>
        <p class="smo-page-subtitle">
            <?php _e('Create consistent, professional content with pre-designed templates', 'smo-social'); ?>
        </p>
    </div>
    <div class="smo-header-actions">
        <button type="button" class="smo-btn smo-btn-secondary" id="smo-import-templates">
            <span class="dashicons dashicons-upload"></span>
            <?php _e('Import Templates', 'smo-social'); ?>
        </button>
        <button type="button" class="smo-btn smo-btn-primary" id="smo-create-new-template">
            <span class="dashicons dashicons-plus"></span>
            <?php _e('Create Template', 'smo-social'); ?>
        </button>
    </div>
</div>

<!-- Dashboard Stats Overview -->
<div class="smo-import-dashboard">
    <div class="smo-stats-grid">
        <div class="smo-stat-card smo-stat-gradient-1">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-admin-page"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html($total_templates); ?></h3>
                <p class="smo-stat-label"><?php _e('Total Templates', 'smo-social'); ?></p>
                <span class="smo-stat-trend">📋 Available</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-2">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html($total_usage); ?></h3>
                <p class="smo-stat-label"><?php _e('Total Uses', 'smo-social'); ?></p>
                <span class="smo-stat-trend">📈 All Time</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-3">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html($avg_usage); ?></h3>
                <p class="smo-stat-label"><?php _e('Avg Uses', 'smo-social'); ?></p>
                <span class="smo-stat-trend">⭐ Per Template</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-4">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-category"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html($total_categories); ?></h3>
                <p class="smo-stat-label"><?php _e('Categories', 'smo-social'); ?></p>
                <span class="smo-stat-trend">🗂️ Organized</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="smo-quick-actions">
    <button class="smo-quick-action-btn" id="smo-filter-all">
        <span class="dashicons dashicons-admin-page"></span>
        <span><?php _e('All Templates', 'smo-social'); ?></span>
    </button>
    <button class="smo-quick-action-btn" id="smo-filter-popular">
        <span class="dashicons dashicons-star-filled"></span>
        <span><?php _e('Most Used', 'smo-social'); ?></span>
    </button>
    <button class="smo-quick-action-btn" id="smo-filter-recent">
        <span class="dashicons dashicons-clock"></span>
        <span><?php _e('Recently Added', 'smo-social'); ?></span>
    </button>
    <button class="smo-quick-action-btn" id="smo-export-templates">
        <span class="dashicons dashicons-download"></span>
        <span><?php _e('Export All', 'smo-social'); ?></span>
    </button>
</div>

<!-- Main Content -->
<div class="smo-card">
    <div class="smo-card-header">
        <h2 class="smo-card-title">
            <span class="dashicons dashicons-list-view"></span>
            <?php _e('Available Templates', 'smo-social'); ?>
        </h2>
        <div class="smo-card-actions">
            <select class="smo-form-select" id="smo-category-filter" style="width: 200px;">
                <option value=""><?php _e('All Categories', 'smo-social'); ?></option>
                <?php foreach ($categories as $cat_key => $cat_name): ?>
                    <option value="<?php echo esc_attr($cat_key); ?>"><?php echo esc_html($cat_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="smo-card-body">
        <div class="smo-templates-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
            <?php foreach ($templates as $template): ?>
                <div class="smo-template-card-enhanced">
                    <div class="smo-template-header">
                        <h3><?php echo esc_html($template['name']); ?></h3>
                        <span class="smo-usage-badge"><?php echo esc_html($template['usage_count']); ?> uses</span>
                    </div>
                    <p class="smo-template-description"><?php echo esc_html($template['description']); ?></p>
                    
                    <div class="smo-template-platforms">
                        <?php foreach ($template['platforms'] as $platform): ?>
                            <span class="smo-platform-badge">
                                <span class="dashicons dashicons-<?php echo esc_attr($platform); ?>"></span>
                                <?php echo esc_html(ucfirst($platform)); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="smo-template-content">
                        <h4><?php _e('Preview:', 'smo-social'); ?></h4>
                        <div class="smo-template-preview"><?php echo esc_html($template['content']); ?></div>
                    </div>
                    
                    <div class="smo-template-actions">
                        <button class="smo-btn smo-btn-primary smo-use-template" data-template="<?php echo esc_attr(json_encode($template)); ?>">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Use Template', 'smo-social'); ?>
                        </button>
                        <button class="smo-btn smo-btn-secondary smo-edit-template" data-template-id="<?php echo esc_attr($template['id']); ?>">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Edit', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($templates)): ?>
                <div class="smo-empty-state" style="grid-column: 1 / -1;">
                    <p><?php _e('No templates found. Create your first template to get started!', 'smo-social'); ?></p>
                    <button class="smo-btn smo-btn-primary" id="smo-create-first-template">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Create Template', 'smo-social'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create/Edit Template Modal -->
<div id="smo-template-modal" class="smo-modal" style="display: none;">
    <div class="smo-modal-content" style="max-width: 600px;">
        <div class="smo-modal-header">
            <h3><?php _e('Create New Template', 'smo-social'); ?></h3>
            <button type="button" class="smo-modal-close">&times;</button>
        </div>
        <div class="smo-modal-body">
            <form id="smo-create-template-form">
                <div class="smo-form-group">
                    <label class="smo-form-label" for="template_name"><?php _e('Template Name', 'smo-social'); ?></label>
                    <input type="text" id="template_name" name="template_name" class="smo-form-input" required>
                </div>
                
                <div class="smo-form-group">
                    <label class="smo-form-label" for="template_description"><?php _e('Description', 'smo-social'); ?></label>
                    <textarea id="template_description" name="template_description" class="smo-form-input" rows="2"></textarea>
                </div>
                
                <div class="smo-form-group">
                    <label class="smo-form-label" for="template_category"><?php _e('Category', 'smo-social'); ?></label>
                    <select id="template_category" name="template_category" class="smo-form-select">
                        <?php foreach ($categories as $cat_key => $cat_name): ?>
                            <option value="<?php echo esc_attr($cat_key); ?>"><?php echo esc_html($cat_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="smo-form-group">
                    <label class="smo-form-label" for="template_content"><?php _e('Content Template', 'smo-social'); ?></label>
                    <textarea id="template_content" name="template_content" class="smo-form-input" rows="6" placeholder="Use {VARIABLE_NAME} for placeholders"></textarea>
                    <p class="description"><?php _e('Use variables like {PRODUCT_NAME}, {DATE}, {LINK} that can be replaced when creating posts.', 'smo-social'); ?></p>
                </div>
                
                <div class="smo-form-group">
                    <label class="smo-form-label"><?php _e('Supported Platforms', 'smo-social'); ?></label>
                    <div class="smo-inline-grid-2">
                        <?php
                        $platforms = array('twitter', 'facebook', 'linkedin', 'instagram', 'youtube', 'tiktok');
                        foreach ($platforms as $platform):
                        ?>
                            <label class="smo-inline-flex">
                                <input type="checkbox" name="template_platforms[]" value="<?php echo esc_attr($platform); ?>">
                                <?php echo esc_html(ucfirst($platform)); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="smo-form-actions">
                    <button type="submit" class="smo-btn smo-btn-primary smo-w-full">
                        <?php _e('Save Template', 'smo-social'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_end();
}
?>


<script>
jQuery(document).ready(function($) {
    // Open create template modal
    $('#smo-create-new-template, #smo-create-first-template').on('click', function() {
        $('#smo-template-modal').show();
    });

    // Close modal
    $('.smo-modal-close').on('click', function() {
        $('.smo-modal').hide();
    });

    // Template selection
    $('.smo-use-template').on('click', function() {
        const template = $(this).data('template');
        const params = new URLSearchParams({
            template: JSON.stringify(template),
            action: 'create_with_template'
        });
        window.location.href = '<?php echo admin_url('admin.php?page=smo-social-create'); ?>&' + params.toString();
    });

    // Create template form
    $('#smo-create-template-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'smo_save_template',
            nonce: '<?php echo wp_create_nonce("smo_templates_nonce"); ?>',
            name: $('#template_name').val(),
            description: $('#template_description').val(),
            category: $('#template_category').val(),
            content: $('#template_content').val(),
            platforms: $('input[name="template_platforms[]"]:checked').map(function() {
                return this.value;
            }).get()
        };
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('<?php _e("Template saved successfully!", "smo-social"); ?>');
                    location.reload();
                } else {
                    alert('<?php _e("Error saving template", "smo-social"); ?>');
                }
            }
        });
    });

    // Category filter
    $('#smo-category-filter').on('change', function() {
        const category = $(this).val();
        // Filter templates by category
        $('.smo-template-card-enhanced').each(function() {
            if (!category || $(this).data('category') === category) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Quick filters
    $('#smo-filter-popular').on('click', function() {
        // Sort by usage count
        const $grid = $('.smo-templates-grid');
        const $cards = $grid.children('.smo-template-card-enhanced').sort(function(a, b) {
            const usageA = parseInt($(a).find('.smo-usage-badge').text());
            const usageB = parseInt($(b).find('.smo-usage-badge').text());
            return usageB - usageA;
        });
        $grid.html($cards);
    });
});
</script>