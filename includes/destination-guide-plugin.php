<?php
/**
 * Plugin Name: Destination Guide
 * Plugin URI: https://nerdypappy.com/destination-guide/
 * Description: A WordPress plugin to manage and display OpenSimulator destinations.
 * Version: 1.0.0
 * Author: NerdyPappy
 * Text Domain: destination-guide
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('DESTINATION_GUIDE_VERSION', '1.0.0');
define('DESTINATION_GUIDE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DESTINATION_GUIDE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DESTINATION_GUIDE_ADMIN_URL', admin_url('admin.php?page=destination-guide'));
define('DESTINATION_GUIDE_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/destination-guide/');
define('DESTINATION_GUIDE_UPLOAD_URL', wp_upload_dir()['baseurl'] . '/destination-guide/');

// Include necessary files
require_once DESTINATION_GUIDE_PLUGIN_DIR . 'includes/database.php';
require_once DESTINATION_GUIDE_PLUGIN_DIR . 'includes/class-destination-guide.php';
require_once DESTINATION_GUIDE_PLUGIN_DIR . 'admin/admin-page.php';

// Register activation hook
register_activation_hook(__FILE__, 'destination_guide_activate');

/**
 * Plugin activation function
 */
function destination_guide_activate() {
    // Create database tables
    destination_guide_create_tables();
    
    // Create upload directory if it doesn't exist
    if (!file_exists(DESTINATION_GUIDE_UPLOAD_DIR)) {
        wp_mkdir_p(DESTINATION_GUIDE_UPLOAD_DIR);
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'destination_guide_deactivate');

function destination_guide_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Initialize the plugin
 */
function destination_guide_init() {
    // Register scripts and styles
    add_action('wp_enqueue_scripts', 'destination_guide_register_scripts');
    
    // Register shortcode
    add_shortcode('destination_guide', 'destination_guide_shortcode');
}
add_action('init', 'destination_guide_init');

/**
 * Register scripts and styles
 */
function destination_guide_register_scripts() {
    // Register CSS
    wp_register_style(
        'destination-guide-css',
        DESTINATION_GUIDE_PLUGIN_URL . 'public/css/destination-guide.css',
        array(),
        DESTINATION_GUIDE_VERSION
    );
    
    // Register JS
    wp_register_script(
        'destination-guide-js',
        DESTINATION_GUIDE_PLUGIN_URL . 'public/js/destination-guide.js',
        array('jquery'),
        DESTINATION_GUIDE_VERSION,
        true
    );
}

/**
 * Shortcode function to display the destination guide
 */
function destination_guide_shortcode($atts) {
    // Enqueue required styles and scripts
    wp_enqueue_style('destination-guide-css');
    wp_enqueue_script('destination-guide-js');
    
    // Extract shortcode attributes
    $atts = shortcode_atts(
        array(
            'grid' => 'all',
            'limit' => -1,
            'category' => 'all',
        ),
        $atts,
        'destination_guide'
    );
    
    // Get destinations
    $destination_guide = new Destination_Guide();
    $destinations = $destination_guide->get_destinations($atts);
    
    // Start output buffering
    ob_start();
    
    // Include template
    include DESTINATION_GUIDE_PLUGIN_DIR . 'public/templates/destination-list.php';
    
    // Return the buffered content
    return ob_get_clean();
}
