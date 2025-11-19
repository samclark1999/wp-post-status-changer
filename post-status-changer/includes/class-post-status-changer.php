<?php

/**
 * Main plugin class
 */
class Post_Status_Changer
{

    /**
     * Initialize the plugin
     */
    public function init()
    {
        // Register admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));

        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Register AJAX handlers
        add_action('wp_ajax_psc_process_status_change', array($this, 'process_status_change'));
    }

    /**
     * Register the admin menu
     */
    public function register_admin_menu()
    {
        add_management_page(
            __('Post Status Changer', 'post-status-changer'),
            __('Post Status Changer', 'post-status-changer'),
            'manage_options',
            'post-status-changer',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook)
    {
        if ('tools_page_post-status-changer' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'post-status-changer-admin',
            PSC_PLUGIN_URL . 'assets/css/post-status-changer-admin.css',
            array(),
            PSC_VERSION
        );

        wp_enqueue_script(
            'post-status-changer-admin',
            PSC_PLUGIN_URL . 'assets/js/post-status-changer-admin.js',
            array('jquery'),
            PSC_VERSION,
            true
        );

        wp_localize_script(
            'post-status-changer-admin',
            'pscData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('psc_nonce'),
                'processing' => __('Processing...', 'post-status-changer'),
                'success' => __('Status changed successfully!', 'post-status-changer'),
                'dryRunCompleted' => __('Dry run completed - No changes applied', 'post-status-changer'),
                'error' => __('An error occurred.', 'post-status-changer')
            )
        );
    }

    /**
     * Render the admin page
     */
    public function render_admin_page()
    {
        // Get all registered post types
        $post_types = get_post_types(array('public' => true), 'objects');

        // Get all post statuses
        $statuses = get_post_statuses();
        $statuses['private'] = __('Private', 'post-status-changer');
        $statuses['password'] = __('Password Protected', 'post-status-changer');

        // Get max recommended batch size based on server memory limit
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_mb = $memory_limit / (1024 * 1024);

        // Calculate recommended batch size
        // More generous allocation: ~0.5MB per post (includes overhead)
        $recommended_batch = min(1000, max(250, floor($memory_mb / 0.5)));

        // Round to a nice number
        if ($recommended_batch > 500) {
            $recommended_batch = floor($recommended_batch / 100) * 100;
        } else {
            $recommended_batch = floor($recommended_batch / 50) * 50;
        }

        include PSC_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Process the status change via AJAX
     */
    public function process_status_change()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'psc_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'post-status-changer')));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'post-status-changer')));
        }

        // Validate inputs
        if (empty($_POST['post_type']) || empty($_FILES['csv_file'])) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'post-status-changer')));
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        $action = isset($_POST['psc_action']) ? sanitize_text_field($_POST['psc_action']) : 'change_status';
        $status = '';
        if ($action === 'change_status') {
            $status = sanitize_text_field($_POST['status']);
        }
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';

        // Get batch size (if provided)
        $batch_size = isset($_POST['batch_size']) && is_numeric($_POST['batch_size'])
            ? intval($_POST['batch_size'])
            : 0;

        // Validate batch size
        if ($batch_size < 50) {
            $batch_size = 0; // Use default
        } elseif ($batch_size > 5000) {
            $batch_size = 5000; // Cap at 5000 for safety
        }

        $mode = isset($_POST['psc_mode']) ? sanitize_text_field($_POST['psc_mode']) : 'csv';
        $regex = isset($_POST['psc_regex']) ? sanitize_text_field($_POST['psc_regex']) : '';
        $regex_field = isset($_POST['psc_regex_field']) ? sanitize_text_field($_POST['psc_regex_field']) : 'post_title';

        if ($mode === 'csv' && (empty($_FILES['csv_file']) || empty($_FILES['csv_file']['tmp_name']))) {
            wp_send_json_error(array('message' => __('CSV file is required.', 'post-status-changer')));
        }
        if ($mode === 'regex' && empty($regex)) {
            wp_send_json_error(array('message' => __('Regex/Post Search is required.', 'post-status-changer')));
        }

        // Process the CSV file
        $processor = new Post_Status_Processor();
        $result = $processor->process_posts([
            'mode' => $mode,
            'csv_file' => $_FILES['csv_file'],
            'regex' => $regex,
            'regex_field' => $regex_field,
            'post_type' => $post_type,
            'status' => $action === 'change_status' ? $status : 'trash',
            'dry_run' => $dry_run,
            'batch_size' => $batch_size
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Add dry run info to result
        $result['dry_run'] = $dry_run;

        wp_send_json_success($result);
    }
}
