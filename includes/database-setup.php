<?php
/**
 * Database functions for Destination Guide plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the necessary database tables
 */
function destination_guide_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Define table name
    $table_name = $wpdb->prefix . 'destination_guide';
    
    // SQL to create the destinations table
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        description text NOT NULL,
        grid varchar(100) NOT NULL,
        category varchar(100) NOT NULL,
        region varchar(100) NOT NULL,
        image varchar(255) NOT NULL,
        teleport_url varchar(255) NOT NULL,
        x_coord int(11) DEFAULT NULL,
        y_coord int(11) DEFAULT NULL,
        z_coord int(11) DEFAULT NULL,
        featured tinyint(1) DEFAULT 0,
        created_date datetime DEFAULT CURRENT_TIMESTAMP,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Import existing CSV data into the database
 */
function destination_guide_import_csv($file_path) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'destination_guide';
    
    if (!file_exists($file_path)) {
        return false;
    }
    
    // Open the CSV file
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        // Get header row
        $header = fgetcsv($handle);
        
        // Process data rows
        while (($data = fgetcsv($handle)) !== FALSE) {
            // Map CSV columns to database fields
            // This mapping will depend on your CSV structure
            $destination = array(
                'name' => isset($data[0]) ? sanitize_text_field($data[0]) : '',
                'description' => isset($data[1]) ? sanitize_textarea_field($data[1]) : '',
                'grid' => isset($data[2]) ? sanitize_text_field($data[2]) : '',
                'category' => isset($data[3]) ? sanitize_text_field($data[3]) : '',
                'region' => isset($data[4]) ? sanitize_text_field($data[4]) : '',
                'image' => isset($data[5]) ? sanitize_text_field($data[5]) : '',
                'teleport_url' => isset($data[6]) ? esc_url_raw($data[6]) : '',
                'x_coord' => isset($data[7]) ? intval($data[7]) : null,
                'y_coord' => isset($data[8]) ? intval($data[8]) : null,
                'z_coord' => isset($data[9]) ? intval($data[9]) : null,
                'featured' => isset($data[10]) ? intval($data[10]) : 0
            );
            
            // Insert into database
            $wpdb->insert($table_name, $destination);
        }
        fclose($handle);
        return true;
    }
    
    return false;
}

/**
 * Insert a new destination
 */
function destination_guide_insert_destination($destination_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'destination_guide';
    
    // Sanitize data
    $data = array(
        'name' => sanitize_text_field($destination_data['name']),
        'description' => sanitize_textarea_field($destination_data['description']),
        'grid' => sanitize_text_field($destination_data['grid']),
        'category' => sanitize_text_field($destination_data['category']),
        'region' => sanitize_text_field($destination_data['region']),
        'image' => sanitize_text_field($destination_data['image']),
        'teleport_url' => esc_url_raw($destination_data['teleport_url']),
        'x_coord' => intval($destination_data['x_coord']),
        'y_coord' => intval($destination_data['y_coord']),
        'z_coord' => intval($destination_data['z_coord']),
        'featured' => isset($destination_data['featured']) ? 1 : 0
    );
    
    // Insert into database
    $result = $wpdb->insert($table_name, $data);
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return false;
}

/**
 * Update an existing destination
 */
function destination_guide_update_destination($id, $destination_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'destination_guide';
    
    // Sanitize data
    $data = array(
        'name' => sanitize_text_field($destination_data['name']),
        'description' => sanitize_textarea_field($destination_data['description']),
        'grid' => sanitize_text_field($destination_data['grid']),
        'category' => sanitize_text_field($destination_data['category']),
        'region' => sanitize_text_field($destination_data['region']),
        'image' => sanitize_text_field($destination_data['image']),
        'teleport_url' => esc_url_raw($destination_data['teleport_url']),
        'x_coord' => intval($destination_data['x_coord']),
        'y_coord' => intval($destination_data['y_coord']),
        'z_coord' => intval($destination_data['z_coord']),
        'featured' => isset($destination_data['featured']) ? 1 : 0
    );
    
    // Update database
    $result = $wpdb->update(
        $table_name,
        $data,
        array('id' => intval($id))
    );
    
    return $result !== false;
}

/**
 * Delete a destination
 */
function destination_guide_delete_destination($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'destination_guide';
    
    return $wpdb->delete(
        $table_name,
        array('id' => intval($id))
    );
}

/**
 * Get all destinations or filtered by parameters
 */
function destination_guide_get_destinations($args = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'destination_guide';
    
    // Default arguments
    $defaults = array(
        'grid' => 'all',
        'category' => 'all',
        'featured' => null,
        'limit' => -1,
        'orderby' => 'name',
        'order' => 'ASC'
    );
    
    // Parse arguments
    $args = wp_parse_args($args, $defaults);
    
    // Start building query
    $query = "SELECT * FROM $table_name WHERE 1=1";
    
    // Add filters
    if ($args['grid'] !== 'all') {
        $query .= $wpdb->prepare(" AND grid = %s", $args['grid']);
    }
    
    if ($args['category'] !== 'all') {
        $query .= $wpdb->prepare(" AND category = %s", $args['category']);
    }
    
    if ($args['featured'] !== null) {
        $query .= $wpdb->prepare(" AND featured = %d", intval($args['featured']));
    }
    
    // Add ordering
    $query .= " ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
    
    // Add limit
    if ($args['limit'] > 0) {
        $query .= $wpdb->prepare(" LIMIT %d", intval($args['limit']));
    }
    
    // Execute query
    $results = $wpdb->get_results($query, ARRAY_A);
    
    return $results;
}

/**
 * Get a single destination by ID
 */
function destination_guide_get_destination($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'destination_guide';
    
    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($id)),
        ARRAY_A
    );
}

/**
 * Get unique grid names
 */
function destination_guide_get_grids() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'destination_guide';
    
    return $wpdb->get_col("SELECT DISTINCT grid FROM $table_name ORDER BY grid ASC");
}

/**
 * Get unique category names
 */
function destination_guide_get_categories() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'destination_guide';
    
    return $wpdb->get_col("SELECT DISTINCT category FROM $table_name ORDER BY category ASC");
}
