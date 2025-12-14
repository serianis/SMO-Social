<?php
/**
 * SMO Social Dashboard Overview Widget
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="smo-dashboard-widget">
    <h3><?php _e('SMO Social Quick Stats', 'smo-social'); ?></h3>
    
    <div class="smo-widget-stats">
        <div class="smo-widget-stat">
            <span class="smo-stat-label"><?php _e('Scheduled Posts', 'smo-social'); ?></span>
            <span class="smo-stat-value"><?php echo isset($stats['scheduled_posts']) ? $stats['scheduled_posts'] : '0'; ?></span>
        </div>
        
        <div class="smo-widget-stat">
            <span class="smo-stat-label"><?php _e('Queue Items', 'smo-social'); ?></span>
            <span class="smo-stat-value"><?php echo isset($stats['pending_queue']) ? $stats['pending_queue'] : '0'; ?></span>
        </div>
        
        <div class="smo-widget-stat">
            <span class="smo-stat-label"><?php _e('Published Today', 'smo-social'); ?></span>
            <span class="smo-stat-value"><?php echo isset($stats['published_today']) ? $stats['published_today'] : '0'; ?></span>
        </div>
        
        <div class="smo-widget-stat">
            <span class="smo-stat-label"><?php _e('Failed Posts', 'smo-social'); ?></span>
            <span class="smo-stat-value"><?php echo isset($stats['failed_posts']) ? $stats['failed_posts'] : '0'; ?></span>
        </div>
    </div>

    <?php if (isset($stats['posts_per_day'])): ?>
    <div class="smo-widget-posts-per-day">
        <h4><?php _e('Posts Per Day', 'smo-social'); ?></h4>
        <div class="smo-widget-day-stats">
            <div class="smo-widget-day-stat">
                <span class="smo-widget-day-number"><?php echo $stats['posts_per_day']['today']; ?></span>
                <span class="smo-widget-day-label"><?php _e('Today', 'smo-social'); ?></span>
            </div>
            <div class="smo-widget-day-stat">
                <span class="smo-widget-day-number"><?php echo $stats['posts_per_day']['avg_7_days']; ?></span>
                <span class="smo-widget-day-label"><?php _e('Avg (7d)', 'smo-social'); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="smo-widget-actions">
        <a href="<?php echo admin_url('admin.php?page=smo-social-create'); ?>" class="button button-primary">
            <?php _e('Create Post', 'smo-social'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=smo-social-posts'); ?>" class="button">
            <?php _e('View All Posts', 'smo-social'); ?>
        </a>
    </div>
    
    <div class="smo-widget-footer">
        <p><?php _e('Last updated:', 'smo-social'); ?> <?php echo current_time('mysql'); ?></p>
    </div>
</div>

<style>
.smo-dashboard-widget {
    padding: 15px;
}

.smo-widget-stats {
    display: flex;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.smo-widget-stat {
    flex: 1;
    min-width: 120px;
    margin: 5px;
    text-align: center;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.smo-stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.smo-stat-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.smo-widget-actions {
    margin-bottom: 15px;
}

.smo-widget-actions .button {
    margin-right: 10px;
}

.smo-widget-footer {
    border-top: 1px solid #e1e1e1;
    padding-top: 10px;
    font-size: 11px;
    color: #666;
}

.smo-widget-posts-per-day {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e1e1e1;
}

.smo-widget-posts-per-day h4 {
    margin: 0 0 10px 0;
    font-size: 13px;
    color: #646970;
}

.smo-widget-day-stats {
    display: flex;
    justify-content: space-between;
}

.smo-widget-day-stat {
    text-align: center;
}

.smo-widget-day-number {
    display: block;
    font-size: 18px;
    font-weight: bold;
    color: #0073aa;
}

.smo-widget-day-label {
    display: block;
    font-size: 11px;
    color: #646970;
    margin-top: 2px;
}
</style>