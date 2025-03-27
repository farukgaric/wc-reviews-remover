<?php
/**
 * Plugin Name: WooCommerce Reviews Remover
 * Description: Removes all WooCommerce reviews via background process
 * Version: 0.0.1
 * Author: Faruk Garić
 * Author URI: https://www.farukgaric.com/
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Reviews_Remover {
    private $batch_size = 50; // Number of reviews to process per batch
    
       /**
     * Initialize the remover
     */
    public function __construct() {
        add_action('admin_init', array($this, 'maybe_handle_removal'));
        add_action('wp_ajax_wc_remove_reviews', array($this, 'ajax_remove_reviews'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',              // Parent slug (WooCommerce menu)
            'Remove Reviews',           // Page title
            'Remove Reviews',           // Menu title
            'manage_options',           // Capability required
            'wc-reviews-remover',       // Menu slug
            array($this, 'render_admin_page') // Callback function
        );
    }
    
    /**
     * Render the admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Remove WooCommerce Reviews</h1>
            <p>Click the button below to remove all WooCommerce product reviews. This action cannot be undone.</p>
            <form method="get">
                <input type="hidden" name="wc_remove_reviews" value="1">
                <?php wp_nonce_field('wc_remove_reviews'); ?>
                <p>
                    <button type="submit" class="button button-primary" onclick="return confirm('Are you sure you want to remove all reviews? This action cannot be undone.');">
                        Remove All Reviews
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Check if we need to start the removal process
     */
    public function maybe_handle_removal() {
        if (isset($_GET['wc_remove_reviews']) && current_user_can('manage_options')) {
            check_admin_referer('wc_remove_reviews');
            $this->start_removal_process();
        }
    }
    
    /**
     * Start the removal process via AJAX
     */
    private function start_removal_process() {
        // Get total count of reviews
        $total = $this->get_total_reviews_count();
        
        // Enqueue our script
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Show progress UI
        $this->show_progress_ui($total);
        exit;
    }
    
    /**
     * Enqueue necessary scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script('wc-reviews-remover', plugins_url('wc-reviews-remover.js', __FILE__), array('jquery'), '1.0', true);
        
        wp_localize_script('wc-reviews-remover', 'wc_reviews_remover_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wc_remove_reviews_nonce'),
            'total'    => $this->get_total_reviews_count(),
            'batch_size' => $this->batch_size
        ));
    }
    
    /**
     * Get total number of reviews
     */
    private function get_total_reviews_count() {
        global $wpdb;
        return (int) $wpdb->get_var("
            SELECT COUNT(*)
            FROM $wpdb->comments
            WHERE comment_type = 'review'
        ");
    }
    
    /**
     * Display progress UI
     */
    private function show_progress_ui($total) {
        ?>
        <div class="wrap">
            <h1>Remove WooCommerce Reviews</h1>
            <div id="wc-reviews-remover-progress">
                <p>Removing reviews in background. Please keep this page open.</p>
                <div class="progress-bar">
                    <div class="progress" style="width: 0%"></div>
                </div>
                <p>Processed <span class="processed">0</span> of <span class="total"><?php echo $total; ?></span> reviews</p>
                <p class="status">Starting...</p>
            </div>
        </div>
        <style>
            .progress-bar {
                background: #f1f1f1;
                height: 30px;
                margin: 20px 0;
                width: 100%;
            }
            .progress-bar .progress {
                background: #2271b1;
                height: 100%;
                transition: width 0.5s ease;
            }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for removing reviews
     */
    public function ajax_remove_reviews() {
        check_ajax_referer('wc_remove_reviews_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
        $processed = isset($_POST['processed']) ? (int) $_POST['processed'] : 0;
        $total = isset($_POST['total']) ? (int) $_POST['total'] : $this->get_total_reviews_count();
        
        // Get a batch of review IDs
        $review_ids = $this->get_review_batch($offset, $this->batch_size);
        
        if (empty($review_ids)) {
            wp_send_json_success(array(
                'complete' => true,
                'processed' => $processed,
                'message'   => 'All reviews have been removed successfully!'
            ));
        }
        
        // Remove this batch of reviews
        foreach ($review_ids as $review_id) {
            wp_delete_comment($review_id, true);
            $processed++;
        }
        
        $progress = ($total > 0) ? round(($processed / $total) * 100) : 100;
        
        wp_send_json_success(array(
            'complete'  => false,
            'processed' => $processed,
            'progress' => $progress,
            'offset'   => $offset + $this->batch_size,
            'message'   => sprintf('Processed %d of %d reviews', $processed, $total)
        ));
    }
    
    /**
     * Get a batch of review IDs
     */
    private function get_review_batch($offset, $limit) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare("
            SELECT comment_ID
            FROM $wpdb->comments
            WHERE comment_type = 'review'
            ORDER BY comment_ID ASC
            LIMIT %d, %d
        ", $offset, $limit));
    }
}

new WC_Reviews_Remover();