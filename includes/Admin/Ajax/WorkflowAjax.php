<?php
namespace SMO_Social\Admin\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Workflow AJAX Handler
 * 
 * Handles all AJAX requests related to Approval Workflows
 */
class WorkflowAjax extends BaseAjaxHandler {
    
    /**
     * Override nonce action to match frontend
     * @var string
     */
    protected $nonce_action = 'smo_users_nonce'; 

    /**
     * Register AJAX actions
     */
    public function register() {
        $actions = [
            'smo_get_workflows' => 'ajax_get_workflows',
            'smo_save_workflow' => 'ajax_save_workflow',
            'smo_delete_workflow' => 'ajax_delete_workflow'
        ];

        foreach ($actions as $action => $method) {
            add_action('wp_ajax_' . $action, [$this, $method]);
        }
    }

    /**
     * AJAX: Get approval workflows
     */
    public function ajax_get_workflows() {
        if (!$this->verify_request()) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_approval_workflows';
        
        $workflows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);
        
        foreach ($workflows as &$workflow) {
            $approvers = json_decode($workflow['approvers'], true);
            $workflow['approvers_count'] = is_array($approvers) ? count($approvers) : 0;
            $workflow['approvers_list'] = $approvers;
        }
        
        $this->send_success(['workflows' => $workflows]);
    }

    /**
     * AJAX: Save approval workflow
     */
    public function ajax_save_workflow() {
        if (!$this->verify_request()) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_approval_workflows';
        
        $id = $this->get_int('workflow_id');
        $name = $this->get_text('name');
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $required = $this->get_int('required_approvals', 1);
        $approvers = isset($_POST['approvers']) ? array_map('intval', $_POST['approvers']) : [];
        
        if (empty($name) || empty($approvers)) {
            $this->send_error(__('Name and at least one approver are required', 'smo-social'));
            return;
        }
        
        $data = [
            'name' => $name,
            'description' => $description,
            'required_approvals' => $required,
            'approvers' => json_encode($approvers),
            'updated_at' => current_time('mysql')
        ];
        
        if ($id > 0) {
            $result = $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
        }
        
        if ($result !== false) {
            $this->send_success(['message' => __('Workflow saved successfully', 'smo-social')]);
        } else {
            $this->send_error(__('Failed to save workflow', 'smo-social'));
        }
    }

    /**
     * AJAX: Delete approval workflow
     */
    public function ajax_delete_workflow() {
        if (!$this->verify_request()) {
            return;
        }
        
        $id = $this->get_int('id');
        global $wpdb;
        $table = $wpdb->prefix . 'smo_approval_workflows';
        
        $result = $wpdb->delete($table, ['id' => $id]);
        
        if ($result) {
            $this->send_success(['message' => __('Workflow deleted', 'smo-social')]);
        } else {
            $this->send_error(__('Failed to delete workflow', 'smo-social'));
        }
    }
}
