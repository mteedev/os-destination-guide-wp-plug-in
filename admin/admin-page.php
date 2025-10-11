<?php
/**
 * Admin page for Destination Guide
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include form handling file
require_once DESTINATION_GUIDE_PLUGIN_DIR . 'admin/destination-form.php';

/**
 * Add admin menu items
 */
function destination_guide_admin_menu() {
    add_menu_page(
        'Destination Guide',
        'Destination Guide',
        'manage_options',
        'destination-guide',
        'destination_guide_admin_page',
        'dashicons-location-alt',
        30
    );
    
    add_submenu_page(
        'destination-guide',
        'All Destinations',
        'All Destinations',
        'manage_options',
        'destination-guide',
        'destination_guide_admin_page'
    );
    
    add_submenu_page(
        'destination-guide',
        'Add New Destination',
        'Add New',
        'manage_options',
        'destination-guide-add',
        'destination_guide_add_page'
    );
    
    add_submenu_page(
        'destination-guide',
        'Import/Export',
        'Import/Export',
        'manage_options',
        'destination-guide-import-export',
        'destination_guide_import_export_page'
    );
    
    add_submenu_page(
        'destination-guide',
        'Settings',
        'Settings',
        'manage_options',
        'destination-guide-settings',
        'destination_guide_settings_page'
    );
}
add_action('admin_menu', 'destination_guide_admin_menu');

/**
 * Register admin scripts and styles
 */
function destination_guide_admin_scripts() {
    wp_register_style(
        'destination-guide-admin-css',
        DESTINATION_GUIDE_PLUGIN_URL . 'admin/css/admin-style.css',
        array(),
        DESTINATION_GUIDE_VERSION
    );
    
    wp_enqueue_style('destination-guide-admin-css');
    
    // WordPress media uploader
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'destination_guide_admin_scripts');

/**
 * Main admin page - List all destinations
 */
function destination_guide_admin_page() {
    // Handle actions (delete, etc.)
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $action = sanitize_text_field($_GET['action']);
        $id = intval($_GET['id']);
        
        if ($action === 'delete' && wp_verify_nonce($_GET['_wpnonce'], 'delete_destination_' . $id)) {
            $destination_guide = new Destination_Guide();
            $result = $destination_guide->delete_destination($id);
            
            if ($result) {
                add_settings_error(
                    'destination_guide',
                    'destination_deleted',
                    'Destination successfully deleted.',
                    'updated'
                );
            } else {
                add_settings_error(
                    'destination_guide',
                    'destination_delete_error',
                    'Error deleting destination.',
                    'error'
                );
            }
        } elseif ($action === 'edit') {
            destination_guide_edit_page($id);
            return;
        }
    }
    
    // Get all destinations
    $destination_guide = new Destination_Guide();
    $destinations = $destination_guide->get_destinations();
    
    // Filter parameters
    $grid_filter = isset($_GET['grid']) ? sanitize_text_field($_GET['grid']) : 'all';
    $category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : 'all';
    
    if ($grid_filter !== 'all' || $category_filter !== 'all') {
        $filter_args = array(
            'grid' => $grid_filter,
            'category' => $category_filter
        );
        $destinations = $destination_guide->get_destinations($filter_args);
    }
    
    // Get filter options
    $grids = $destination_guide->get_grids();
    $categories = $destination_guide->get_categories();
    
    // Display settings errors
    settings_errors('destination_guide');
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Destinations</h1>
        <a href="<?php echo admin_url('admin.php?page=destination-guide-add'); ?>" class="page-title-action">Add New</a>
        
        <div class="destination-guide-filters">
            <form method="get">
                <input type="hidden" name="page" value="destination-guide">
                
                <select name="grid">
                    <option value="all" <?php selected($grid_filter, 'all'); ?>>All Grids</option>
                    <?php foreach ($grids as $grid) : ?>
                        <option value="<?php echo esc_attr($grid); ?>" <?php selected($grid_filter, $grid); ?>>
                            <?php echo esc_html($grid); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="category">
                    <option value="all" <?php selected($category_filter, 'all'); ?>>All Categories</option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr($category); ?>" <?php selected($category_filter, $category); ?>>
                            <?php echo esc_html($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="submit" class="button" value="Filter">
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Grid</th>
                    <th>Category</th>
                    <th>Region</th>
                    <th>Featured</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($destinations)) : ?>
                    <tr>
                        <td colspan="7">No destinations found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($destinations as $destination) : ?>
                        <tr>
                            <td>
                                <?php if (!empty($destination['image'])) : ?>
                                    <img src="<?php echo esc_url($destination['image']); ?>" alt="<?php echo esc_attr($destination['name']); ?>" style="max-width: 100px; max-height: 60px;">
                                <?php else : ?>
                                    <span>No Image</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($destination['name']); ?></td>
                            <td><?php echo esc_html($destination['grid']); ?></td>
                            <td><?php echo esc_html($destination['category']); ?></td>
                            <td><?php echo esc_html($destination['region']); ?></td>
                            <td><?php echo $destination['featured'] ? 'Yes' : 'No'; ?></td>
                            <td>
                                <a href="<?php echo add_query_arg(array('action' => 'edit', 'id' => $destination['id']), admin_url('admin.php?page=destination-guide')); ?>">Edit</a>
                                |
                                <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete', 'id' => $destination['id']), admin_url('admin.php?page=destination-guide')), 'delete_destination_' . $destination['id']); ?>" class="delete" onclick="return confirm('Are you sure you want to delete this destination?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Add new destination page
 */
function destination_guide_add_page() {
    // Process form submission
    if (isset($_POST['destination_guide_submit']) && wp_verify_nonce($_POST['destination_guide_nonce'], 'destination_guide_add')) {
        process_destination_form();
    }
    
    // Display settings errors
    settings_errors('destination_guide');
    ?>
    <div class="wrap">
        <h1>Add New Destination</h1>
        
        <?php destination_guide_form(); ?>
    </div>
    <?php
}

/**
 * Edit destination page
 */
function destination_guide_edit_page($id) {
    // Get destination data
    $destination_guide = new Destination_Guide();
    $destination = $destination_guide->get_destination($id);
    
    if (!$destination) {
        wp_die('Destination not found.');
    }
    
    // Process form submission
    if (isset($_POST['destination_guide_submit']) && wp_verify_nonce($_POST['destination_guide_nonce'], 'destination_guide_edit_' . $id)) {
        process_destination_form($id);
    }
    
    // Display settings errors
    settings_errors('destination_guide');
    ?>
    <div class="wrap">
        <h1>Edit Destination</h1>
        
        <?php destination_guide_form($destination); ?>
    </div>
    <?php
}

/**
 * Import/Export page
 */
function destination_guide_import_export_page() {
    // Handle CSV import
    if (isset($_POST['destination_guide_import']) && wp_verify_nonce($_POST['destination_guide_import_nonce'], 'destination_guide_import')) {
        if (isset($_FILES['import_file']) && !empty($_FILES['import_file']['name'])) {
            $file = $_FILES['import_file'];
            
            // Check file type
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if ($file_ext !== 'csv') {
                add_settings_error(
                    'destination_guide',
                    'invalid_file',
                    'Please upload a valid CSV file.',
                    'error'
                );
            } else {
                // Move the uploaded file to a temporary location
                $upload_dir = wp_upload_dir();
                $temp_file = $upload_dir['basedir'] . '/destination-guide-import-' . time() . '.csv';
                
                if (move_uploaded_file($file['tmp_name'], $temp_file)) {
                    // Import the CSV
                    $result = destination_guide_import_csv($temp_file);
                    
                    // Delete the temporary file
                    @unlink($temp_file);
                    
                    if ($result) {
                        add_settings_error(
                            'destination_guide',
                            'import_success',
                            'CSV file imported successfully.',
                            'updated'
                        );
                    } else {
                        add_settings_error(
                            'destination_guide',
                            'import_error',
                            'Error importing CSV file.',
                            'error'
                        );
                    }
                } else {
                    add_settings_error(
                        'destination_guide',
                        'upload_error',
                        'Error uploading file.',
                        'error'
                    );
                }
            }
        } else {
            add_settings_error(
                'destination_guide',
                'no_file',
                'Please select a CSV file to import.',
                'error'
            );
        }
    }
    
    // Handle CSV export
    if (isset($_POST['destination_guide_export']) && wp_verify_nonce($_POST['destination_guide_export_nonce'], 'destination_guide_export')) {
        $destination_guide = new Destination_Guide();
        
        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="destination-guide-export-' . date('Y-m-d') . '.csv"');
        
        // Export data
        $destination_guide->export_to_csv();
        exit;
    }
    
    // Display settings errors
    settings_errors('destination_guide');
    ?>
    <div class="wrap">
        <h1>Import/Export Destinations</h1>
        
        <div class="card">
            <h2>Import CSV</h2>
            <p>Import destinations from a CSV file.</p>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('destination_guide_import', 'destination_guide_import_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="import_file">CSV File</label></th>
                        <td>
                            <input type="file" name="import_file" id="import_file" accept=".csv">
                            <p class="description">Select a CSV file to import.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="destination_guide_import" class="button button-primary" value="Import CSV">
                </p>
            </form>
        </div>
        
        <div class="card">
            <h2>Export CSV</h2>
            <p>Export all destinations to a CSV file.</p>
            
            <form method="post">
                <?php wp_nonce_field('destination_guide_export', 'destination_guide_export_nonce'); ?>
                
                <p class="submit">
                    <input type="submit" name="destination_guide_export" class="button button-primary" value="Export CSV">
                </p>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Settings page
 */
function destination_guide_settings_page() {
    // Register settings
    if (!get_option('destination_guide_settings')) {
        add_option('destination_guide_settings', array(
            'items_per_page' => 12,
            'default_grid' => '',
            'default_sort' => 'name',
            'default_order' => 'ASC',
            'show_grid_filter' => 1,
            'show_category_filter' => 1
        ));
    }
    
    // Save settings
    if (isset($_POST['destination_guide_settings_submit']) && wp_verify_nonce($_POST['destination_guide_settings_nonce'], 'destination_guide_settings')) {
        $settings = array(
            'items_per_page' => intval($_POST['items_per_page']),
            'default_grid' => sanitize_text_field($_POST['default_grid']),
            'default_sort' => sanitize_text_field($_POST['default_sort']),
            'default_order' => sanitize_text_field($_POST['default_order']),
            'show_grid_filter' => isset($_POST['show_grid_filter']) ? 1 : 0,
            'show_category_filter' => isset($_POST['show_category_filter']) ? 1 : 0
        );
        
        update_option('destination_guide_settings', $settings);
        
        add_settings_error(
            'destination_guide',
            'settings_updated',
            'Settings saved successfully.',
            'updated'
        );
    }
    
    // Get current settings
    $settings = get_option('destination_guide_settings');
    
    // Get grids for default selection
    $destination_guide = new Destination_Guide();
    $grids = $destination_guide->get_grids();
    
    // Display settings errors
    settings_errors('destination_guide');
    ?>
    <div class="wrap">
        <h1>Destination Guide Settings</h1>
        
        <form method="post">
            <?php wp_nonce_field('destination_guide_settings', 'destination_guide_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="items_per_page">Items Per Page</label></th>
                    <td>
                        <input type="number" name="items_per_page" id="items_per_page" value="<?php echo esc_attr($settings['items_per_page']); ?>" min="1" max="100">
                        <p class="description">Number of destinations to display per page.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="default_grid">Default Grid</label></th>
                    <td>
                        <select name="default_grid" id="default_grid">
                            <option value="" <?php selected($settings['default_grid'], ''); ?>>All Grids</option>
                            <?php foreach ($grids as $grid) : ?>
                                <option value="<?php echo esc_attr($grid); ?>" <?php selected($settings['default_grid'], $grid); ?>>
                                    <?php echo esc_html($grid); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Default grid to display when viewing the destination guide.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="default_sort">Default Sort</label></th>
                    <td>
                        <select name="default_sort" id="default_sort">
                            <option value="name" <?php selected($settings['default_sort'], 'name'); ?>>Name</option>
                            <option value="grid" <?php selected($settings['default_sort'], 'grid'); ?>>Grid</option>
                            <option value="category" <?php selected($settings['default_sort'], 'category'); ?>>Category</option>
                            <option value="created_date" <?php selected($settings['default_sort'], 'created_date'); ?>>Date Added</option>
                        </select>
                        <p class="description">Default field to sort destinations by.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="default_order">Default Order</label></th>
                    <td>
                        <select name="default_order" id="default_order">
                            <option value="ASC" <?php selected($settings['default_order'], 'ASC'); ?>>Ascending</option>
                            <option value="DESC" <?php selected($settings['default_order'], 'DESC'); ?>>Descending</option>
                        </select>
                        <p class="description">Default sort order.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Filter Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_grid_filter" value="1" <?php checked($settings['show_grid_filter'], 1); ?>>
                            Show Grid Filter
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="show_category_filter" value="1" <?php checked($settings['show_category_filter'], 1); ?>>
                            Show Category Filter
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="destination_guide_settings_submit" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}