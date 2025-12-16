// Dashboard page
public function display_dashboard() {
error_log('SMO Social: Starting dashboard display');

try {
include_once $this->plugin_path . 'includes/Admin/Views/EnhancedDashboard.php';
error_log('SMO Social: Dashboard view included successfully');

// Instantiate and render the dashboard
if (class_exists('\SMO_Social\Admin\Views\EnhancedDashboard')) {
\SMO_Social\Admin\Views\EnhancedDashboard::render();
error_log('SMO Social: Dashboard rendered successfully');
} else {
error_log('SMO Social: EnhancedDashboard class not found');
echo '<div class="wrap">
    <p>Dashboard class not found</p>
</div>';
}
} catch (\Exception $e) {
error_log('SMO Social: Error including dashboard view: ' . $e->getMessage());
echo '<div class="wrap">
    <p>Error loading dashboard: ' . esc_html($e->getMessage()) . '</p>
</div>';
}
}