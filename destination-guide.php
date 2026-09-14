<?php
/**
 * Plugin Name: Destination Guide
 * Plugin URI: https://nerdypappy.com
 * Description: WordPress plugin to manage destination guide entries with admin interface
 * Version: 1.1.2
 * Author: Gundahar Bravin
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hard cap on category count, so the horizontal category-tile strip in the
// in-world viewer UI doesn't turn into an endless side-scroll. Adjust if needed.
if (!defined('DESTINATION_GUIDE_MAX_CATEGORIES')) {
    define('DESTINATION_GUIDE_MAX_CATEGORIES', 20);
}

class DestinationGuide {
    
    private $table_name;
    private $categories_table;
    
    public function __construct() {
    global $wpdb;
    $this->table_name = $wpdb->prefix . 'destination_guide';
    $this->categories_table = $wpdb->prefix . 'destination_guide_categories';
    
    // Hook into WordPress
    add_action('init', array($this, 'init'));
    add_action('admin_menu', array($this, 'admin_menu'));
    add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
    add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
    add_shortcode('destination_guide', array($this, 'display_guide'));
    
    // ADD THESE TWO LINES:
    add_action('init', array($this, 'add_clean_endpoint'));
    add_action('template_redirect', array($this, 'handle_clean_endpoint'));
    
    // Make sure default/legacy categories exist as real rows (covers upgrades)
    add_action('admin_init', array($this, 'maybe_seed_categories'));
    
    // AJAX handlers - destinations
    add_action('wp_ajax_save_destination', array($this, 'ajax_save_destination'));
    add_action('wp_ajax_delete_destination', array($this, 'ajax_delete_destination'));
    add_action('wp_ajax_get_destination', array($this, 'ajax_get_destination'));
    
    // AJAX handlers - categories
    add_action('wp_ajax_add_category', array($this, 'ajax_add_category'));
    add_action('wp_ajax_rename_category', array($this, 'ajax_rename_category'));
    add_action('wp_ajax_delete_category', array($this, 'ajax_delete_category'));
    add_action('wp_ajax_reorder_category', array($this, 'ajax_reorder_category'));
    
    // Activation hook
    register_activation_hook(__FILE__, array($this, 'create_table'));
}
    
    public function init() {
        // Handle file uploads
        add_action('wp_ajax_upload_destination_image', array($this, 'handle_image_upload'));
    }
    
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            category varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            image varchar(255) NOT NULL,
            slurl text NOT NULL,
            description text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $categories_sql = "CREATE TABLE {$this->categories_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            sort_order int NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($categories_sql);
        
        $this->maybe_seed_categories();
    }
    
    public function maybe_seed_categories() {
        global $wpdb;
        
        // Table might not exist yet if this fires before activation runs
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->categories_table}'");
        if (!$table_exists) {
            return;
        }
        
        $existing_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->categories_table}");
        if ($existing_count > 0) {
            return;
        }
        
        // Seed from the original default categories, plus anything already
        // in use on the destinations table (covers upgrades from earlier versions)
        $defaults = array(
            'Beautiful Places', 'Clubs', 'Educational', 'Free Parcels',
            'Recreation', 'Roleplay', 'Sandboxes', 'Shopping', 'VIP Housing'
        );
        
        $in_use = $wpdb->get_col("SELECT DISTINCT category FROM {$this->table_name}");
        $seed = array_unique(array_merge($defaults, $in_use ? $in_use : array()));
        
        $order = 0;
        foreach ($seed as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $wpdb->insert($this->categories_table, array(
                'name' => $name,
                'sort_order' => $order
            ));
            $order++;
        }
    }
    
    public function get_categories() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->categories_table} ORDER BY sort_order ASC, name ASC");
    }
    
    private function group_destinations_by_category($destinations) {
        $by_category = array();
        foreach ($destinations as $dest) {
            $by_category[$dest->category][] = $dest;
        }
        
        // Order categories by the admin-configured sort order first
        $ordered = array();
        foreach ($this->get_categories() as $cat) {
            if (isset($by_category[$cat->name])) {
                $ordered[$cat->name] = $by_category[$cat->name];
                unset($by_category[$cat->name]);
            }
        }
        
        // Anything left over (orphaned category strings) goes last
        foreach ($by_category as $name => $dests) {
            $ordered[$name] = $dests;
        }
        
        return $ordered;
    }
    
    public function admin_menu() {
        add_menu_page(
            'Destination Guide',
            'Destination Guide',
            'manage_options',
            'destination-guide',
            array($this, 'admin_page'),
            'dashicons-location-alt',
            6
        );
        
        add_submenu_page(
            'destination-guide',
            'Destinations',
            'Destinations',
            'manage_options',
            'destination-guide',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'destination-guide',
            'Categories',
            'Categories',
            'manage_options',
            'destination-guide-categories',
            array($this, 'categories_page')
        );
    }
    
    public function admin_scripts($hook) {
        $allowed_hooks = array(
            'toplevel_page_destination-guide',
            'destination-guide_page_destination-guide-categories'
        );
        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_enqueue_script('destination-guide-admin', plugin_dir_url(__FILE__) . 'admin_js_file.js', array('jquery'), '1.1.0', true);
        wp_localize_script('destination-guide-admin', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('destination_guide_nonce'),
            'max_categories' => DESTINATION_GUIDE_MAX_CATEGORIES
        ));
        wp_enqueue_style('destination-guide-admin', plugin_dir_url(__FILE__) . 'admin.css', array(), '1.1.0');
    }
    
    public function frontend_scripts() {
        // Only load frontend styles when shortcode is used
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'destination_guide')) {
            wp_enqueue_style('destination-guide-frontend', plugin_dir_url(__FILE__) . 'destination-guide-horizontal.css', array(), '1.1.0');
        }
    }
    
    public function admin_page() {
        global $wpdb;
        
        // Get all destinations
        $destinations = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY category, name");
        $categories = $this->group_destinations_by_category($destinations);
        ?>
        <div class="wrap destination-guide-admin-wrap">
            <h1>Destination Guide</h1>
            
            <!-- Shortcode Instructions -->
            <div class="notice notice-info">
                <p><strong>Usage:</strong> To display the destination guide, use the shortcode <code>[destination_guide]</code> in any post or page.</p>
            </div>
            
            <!-- Add/Edit Form -->
            <div class="destination-guide-card">
                <h2 id="form-title">Add New Destination</h2>
                <form id="destination-form">
                    <input type="hidden" id="destination-id" name="destination_id" value="">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="category">Category</label></th>
                            <td>
                                <select id="category" name="category" required class="regular-text">
                                    <option value="">Select Category</option>
                                    <?php foreach ($this->get_categories() as $cat): ?>
                                        <option value="<?php echo esc_attr($cat->name); ?>"><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Need a new one? <a href="<?php echo esc_url(admin_url('admin.php?page=destination-guide-categories')); ?>">Manage categories</a>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="name">Name</label></th>
                            <td><input type="text" id="name" name="name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="image">Image</label></th>
                            <td>
                                <input type="hidden" id="image" name="image">
                                <button type="button" id="upload-image" class="button">Upload Image</button>
                                <div id="image-preview"></div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="slurl">SLURL</label></th>
                            <td><input type="text" id="slurl" name="slurl" class="regular-text" required placeholder="secondlife://..."></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="description">Description</label></th>
                            <td><textarea id="description" name="description" rows="4" cols="50" class="large-text"></textarea></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button-primary" value="Save Destination">
                        <button type="button" id="cancel-edit" class="button" style="display:none;">Cancel</button>
                    </p>
                </form>
            </div>
            
            <!-- Destinations List -->
            <div class="destination-guide-card">
                <h2>Current Destinations (<?php echo count($destinations); ?> total)</h2>
                
                <?php if (empty($destinations)): ?>
                    <p>No destinations added yet. Create your first destination using the form above.</p>
                <?php else: ?>
                    <div class="destination-guide-list">
                        <?php foreach ($categories as $category => $dests): ?>
                            <div class="category-section">
                                <h3 class="category-header"><?php echo esc_html($category); ?> (<?php echo count($dests); ?>)</h3>
                                <div class="destinations-grid">
                                    <?php foreach ($dests as $dest): ?>
                                        <div class="destination-item">
                                            <div class="destination-image">
                                                <?php if ($dest->image): ?>
                                                    <img src="<?php echo esc_url($dest->image); ?>" alt="<?php echo esc_attr($dest->name); ?>">
                                                <?php else: ?>
                                                    <div class="no-image">No Image</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="destination-info">
                                                <h4><?php echo esc_html($dest->name); ?></h4>
                                                <p class="destination-url"><?php echo esc_html(substr($dest->slurl, 0, 40)) . (strlen($dest->slurl) > 40 ? '...' : ''); ?></p>
                                                <p class="destination-desc"><?php echo esc_html(substr($dest->description, 0, 80)) . (strlen($dest->description) > 80 ? '...' : ''); ?></p>
                                            </div>
                                            <div class="destination-actions">
                                                <button class="button button-small edit-destination" data-id="<?php echo $dest->id; ?>">Edit</button>
                                                <button class="button button-small delete-destination" data-id="<?php echo $dest->id; ?>">Delete</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function categories_page() {
        global $wpdb;
        
        $categories = $this->get_categories();
        $total = count($categories);
        $max = DESTINATION_GUIDE_MAX_CATEGORIES;
        
        // Destination counts per category, for display + delete protection
        $counts = array();
        $rows = $wpdb->get_results("SELECT category, COUNT(*) as cnt FROM {$this->table_name} GROUP BY category");
        foreach ($rows as $row) {
            $counts[$row->category] = (int) $row->cnt;
        }
        ?>
        <div class="wrap destination-guide-admin-wrap">
            <h1>Destination Guide Categories</h1>
            
            <div class="notice notice-info">
                <p><strong>Heads up:</strong> Categories show up as a horizontal-scrolling strip inside the in-world viewer UI. Keeping the list to a reasonable size keeps that strip usable &mdash; this install allows up to <strong><?php echo (int) $max; ?></strong> categories. Currently using <strong><span id="dg-category-count"><?php echo (int) $total; ?></span> of <span id="dg-category-max"><?php echo (int) $max; ?></span></strong>.</p>
            </div>
            
            <div class="destination-guide-card">
                <h2>Add New Category</h2>
                <form id="category-add-form">
                    <input type="text" id="new-category-name" name="new_category_name" class="regular-text" placeholder="e.g. Live Music" maxlength="100" <?php echo ($total >= $max) ? 'disabled' : ''; ?>>
                    <button type="submit" id="add-category-btn" class="button button-primary" <?php echo ($total >= $max) ? 'disabled' : ''; ?>>Add Category</button>
                    <p class="description" id="category-limit-msg" style="<?php echo ($total >= $max) ? '' : 'display:none;'; ?>">
                        Category limit reached (<?php echo (int) $max; ?> max). Delete an unused category to add a new one.
                    </p>
                </form>
            </div>
            
            <div class="destination-guide-card">
                <h2>Existing Categories</h2>
                <?php if (empty($categories)): ?>
                    <p>No categories yet. Add one above.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" id="category-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">Order</th>
                                <th>Name</th>
                                <th style="width:120px;">Destinations</th>
                                <th style="width:260px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $i => $cat):
                                $count = isset($counts[$cat->name]) ? $counts[$cat->name] : 0;
                            ?>
                            <tr data-category-id="<?php echo (int) $cat->id; ?>">
                                <td>
                                    <button type="button" class="button button-small move-category-up" <?php echo ($i === 0) ? 'disabled' : ''; ?>>&uarr;</button>
                                    <button type="button" class="button button-small move-category-down" <?php echo ($i === $total - 1) ? 'disabled' : ''; ?>>&darr;</button>
                                </td>
                                <td>
                                    <span class="category-name-display"><?php echo esc_html($cat->name); ?></span>
                                    <input type="text" class="category-name-edit regular-text" style="display:none;" value="<?php echo esc_attr($cat->name); ?>" maxlength="100">
                                </td>
                                <td class="category-dest-count"><?php echo (int) $count; ?></td>
                                <td>
                                    <button type="button" class="button button-small edit-category">Rename</button>
                                    <button type="button" class="button button-small save-category" style="display:none;">Save</button>
                                    <button type="button" class="button button-small cancel-category" style="display:none;">Cancel</button>
                                    <button type="button" class="button button-small delete-category" <?php echo ($count > 0) ? 'disabled title="Reassign or delete destinations in this category first"' : ''; ?>>Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function handle_image_upload() {
        check_ajax_referer('destination_guide_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $uploadedfile = $_FILES['file'];
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            wp_send_json_success($movefile['url']);
        } else {
            wp_send_json_error($movefile['error']);
        }
    }
    
    public function ajax_save_destination() {
        check_ajax_referer('destination_guide_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        $id = intval($_POST['destination_id']);
        $category = sanitize_text_field($_POST['category']);
        $name = sanitize_text_field($_POST['name']);
        $image = sanitize_url($_POST['image']);
        // NOT sanitize_text_field() here - it mangles literal % characters
        // in SLURLs (e.g. turns .../Axiom%20Shipyard/... into .../AxiomShipyard/...).
        // wp_unslash() + trim() is the minimal, safe processing for this field.
        $slurl = trim(wp_unslash($_POST['slurl']));
        $description = sanitize_textarea_field($_POST['description']);
        
        $data = array(
            'category' => $category,
            'name' => $name,
            'image' => $image,
            'slurl' => $slurl,
            'description' => $description
        );
        
        if ($id > 0) {
            $result = $wpdb->update($this->table_name, $data, array('id' => $id));
        } else {
            $result = $wpdb->insert($this->table_name, $data);
        }
        
        if ($result !== false) {
            wp_send_json_success('Destination saved successfully');
        } else {
            wp_send_json_error('Error saving destination');
        }
    }
    
    public function ajax_get_destination() {
        check_ajax_referer('destination_guide_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        $id = intval($_POST['id']);
        $destination = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
        
        if ($destination) {
            wp_send_json_success($destination);
        } else {
            wp_send_json_error('Destination not found');
        }
    }
    
    public function ajax_delete_destination() {
        check_ajax_referer('destination_guide_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        $id = intval($_POST['id']);
        
        $result = $wpdb->delete($this->table_name, array('id' => $id));
        
        if ($result !== false) {
            wp_send_json_success('Destination deleted successfully');
        } else {
            wp_send_json_error('Error deleting destination');
        }
    }
    
    public function ajax_add_category() {
        check_ajax_referer('destination_guide_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        
        if ($name === '') {
            wp_send_json_error('Category name cannot be empty');
        }
        
        $current_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->categories_table}");
        if ($current_total >= DESTINATION_GUIDE_MAX_CATEGORIES) {
            wp_send_json_error('Category limit reached (' . DESTINATION_GUIDE_MAX_CATEGORIES . ' max). Delete an unused category first.');
        }
        
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->categories_table} WHERE name = %s", $name));
        if ($exists) {
            wp_send_json_error('A category with that name already exists');
        }
        
        $max_order = $wpdb->get_var("SELECT MAX(sort_order) FROM {$this->categories_table}");
        $next_order = ($max_order === null) ? 0 : ((int) $max_order + 1);
        
        $result = $wpdb->insert($this->categories_table, array(
            'name' => $name,
            'sort_order' => $next_order
        ));
        
        if ($result !== false) {
            wp_send_json_success(array(
                'id' => $wpdb->insert_id,
                'name' => $name,
                'total' => $current_total + 1
            ));
        } else {
            wp_send_json_error('Error adding category');
        }
    }
    
    public function ajax_rename_category() {
        check_ajax_referer('destination_guide_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $new_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        
        if ($id <= 0 || $new_name === '') {
            wp_send_json_error('Invalid category or name');
        }
        
        $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->categories_table} WHERE id = %d", $id));
        if (!$old) {
            wp_send_json_error('Category not found');
        }
        
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->categories_table} WHERE name = %s AND id != %d", $new_name, $id));
        if ($exists) {
            wp_send_json_error('A category with that name already exists');
        }
        
        $wpdb->update($this->categories_table, array('name' => $new_name), array('id' => $id));
        
        // Keep existing destinations pointed at the renamed category
        $wpdb->update($this->table_name, array('category' => $new_name), array('category' => $old->name));
        
        wp_send_json_success(array('id' => $id, 'name' => $new_name));
    }
    
    public function ajax_delete_category() {
        check_ajax_referer('destination_guide_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $cat = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->categories_table} WHERE id = %d", $id));
        
        if (!$cat) {
            wp_send_json_error('Category not found');
        }
        
        $in_use = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE category = %s", $cat->name));
        if ($in_use > 0) {
            wp_send_json_error('That category has ' . $in_use . ' destination(s) in it. Reassign or delete them first.');
        }
        
        $wpdb->delete($this->categories_table, array('id' => $id));
        
        $remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->categories_table}");
        wp_send_json_success(array('total' => $remaining));
    }
    
    public function ajax_reorder_category() {
        check_ajax_referer('destination_guide_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : '';
        
        $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->categories_table} WHERE id = %d", $id));
        if (!$current) {
            wp_send_json_error('Category not found');
        }
        
        if ($direction === 'up') {
            $neighbor = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->categories_table} WHERE sort_order < %d ORDER BY sort_order DESC LIMIT 1",
                $current->sort_order
            ));
        } elseif ($direction === 'down') {
            $neighbor = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->categories_table} WHERE sort_order > %d ORDER BY sort_order ASC LIMIT 1",
                $current->sort_order
            ));
        } else {
            wp_send_json_error('Invalid direction');
        }
        
        if (!$neighbor) {
            wp_send_json_error('Already at the edge');
        }
        
        // Swap sort_order values
        $wpdb->update($this->categories_table, array('sort_order' => $neighbor->sort_order), array('id' => $current->id));
        $wpdb->update($this->categories_table, array('sort_order' => $current->sort_order), array('id' => $neighbor->id));
        
        wp_send_json_success('Reordered');
    }
    
public function display_guide($atts) {
    global $wpdb;
    
    $destinations = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY category, name");
    
    if (empty($destinations)) {
        return '<div class="destination-guide-empty">No destinations available yet.</div>';
    }
    
    $categories = $this->group_destinations_by_category($destinations);
    
    ob_start();
    ?>
    <div id="DestinationGuide">
        <div id="AboutIcon">
            <a href="#" onclick="toggleDescription(); return false;">?</a>
        </div>
        
        <div id="Description">
            Welcome to the Destination Guide. Browse through our categories and click on any destination to teleport directly there!
        </div>
        
        <!-- Breadcrumb Navigation -->
        <div id="BreadcrumbNav">
            <a href="#" onclick="showCategories(); return false;" id="home-link">Categories</a>
            <span class="separator" id="breadcrumb-sep" style="display: none;">></span>
            <span id="current-category" style="display: none;"></span>
        </div>
        
        <!-- Main Content Area -->
        <div id="MainContent">
            <!-- Category View (Main Menu) -->
            <div id="CategoryView">
                <!-- Categories will be populated here -->
            </div>
            
            <!-- Destination View (Category Contents) -->
            <div id="DestinationView" class="hidden">
                <!-- Destinations will be populated here -->
            </div>
        </div>
        
        <!-- Detail View (Destination Details) -->
        <div id="DetailView">
    <button class="close-detail" onclick="closeDetail()">&times;</button>
    <div class="detail-content">
        <img id="detail-image" class="detail-image" src="" alt="">
        <div class="detail-info">
            <div id="detail-title" class="detail-title"></div>
            <div id="detail-description" class="detail-description"></div>
            <a id="detail-teleport" class="detail-teleport" href="#">TELEPORT</a>
        </div>
    </div>
</div>
    
    <script>
    // Destination data from WordPress
    const destinationData = <?php echo json_encode($categories); ?>;
    
    let currentView = 'categories';
    let currentCategory = '';

    function toggleDescription() {
        const desc = document.getElementById('Description');
        desc.style.display = desc.style.display === 'none' ? 'block' : 'none';
    }

    function initializeGuide() {
        showCategories();
    }

    function showCategories() {
        currentView = 'categories';
        currentCategory = '';
        
        // Update breadcrumb
        document.getElementById('breadcrumb-sep').style.display = 'none';
        document.getElementById('current-category').style.display = 'none';
        
        // Hide other views
        document.getElementById('DestinationView').classList.add('hidden');
        document.getElementById('DetailView').classList.remove('active');
        document.getElementById('CategoryView').classList.remove('hidden');
        
        // Populate categories
        const categoryView = document.getElementById('CategoryView');
        categoryView.innerHTML = '';
        
        for (const [categoryName, destinations] of Object.entries(destinationData)) {
            const categoryTile = document.createElement('div');
            categoryTile.className = 'category-tile';
            categoryTile.onclick = () => showCategory(categoryName);
            
            // Use first destination image as category representative
            const representativeImage = destinations[0]?.image || '';
            
            categoryTile.innerHTML = `
                ${representativeImage ? 
                    `<img src="${representativeImage}" alt="${escapeHtml(categoryName)}">` : 
                    `<div class="no-image-placeholder">No Image</div>`
                }
                <div class="category-name">
                    ${escapeHtml(categoryName)}
                    <div class="category-count">(${destinations.length} ${destinations.length === 1 ? 'item' : 'items'})</div>
                </div>
            `;
            
            categoryView.appendChild(categoryTile);
        }
    }

    function showCategory(categoryName) {
        currentView = 'destinations';
        currentCategory = categoryName;
        
        // Update breadcrumb
        document.getElementById('breadcrumb-sep').style.display = 'inline';
        document.getElementById('current-category').style.display = 'inline';
        document.getElementById('current-category').textContent = categoryName;
        
        // Hide other views
        document.getElementById('CategoryView').classList.add('hidden');
        document.getElementById('DetailView').classList.remove('active');
        document.getElementById('DestinationView').classList.remove('hidden');
        
        // Populate destinations
        const destinationView = document.getElementById('DestinationView');
        destinationView.innerHTML = '';
        
        const destinations = destinationData[categoryName] || [];
        
        if (destinations.length === 0) {
            destinationView.innerHTML = '<div class="destination-guide-empty">No destinations in this category yet.</div>';
            return;
        }
        
        destinations.forEach(destination => {
            const destTile = document.createElement('div');
            destTile.className = 'destination-tile';
            destTile.onclick = (e) => {
                // Prevent detail view when clicking teleport button
                if (!e.target.classList.contains('TPButton')) {
                    showDestinationDetail(destination);
                }
            };
            
            destTile.innerHTML = `
                ${destination.image ? 
                    `<img src="${destination.image}" alt="${escapeHtml(destination.name)}">` : 
                    `<div class="no-image-placeholder">No Image</div>`
                }
                <div class="TPButton" onclick="teleport('${destination.slurl}'); event.stopPropagation();">VISIT</div>
                <div class="destination-name">${escapeHtml(destination.name)}</div>
            `;
            
            destinationView.appendChild(destTile);
        });
    }

    function showDestinationDetail(destination) {
        // Update detail view content
        document.getElementById('detail-image').src = destination.image || '';
        document.getElementById('detail-image').style.display = destination.image ? 'block' : 'none';
        document.getElementById('detail-title').textContent = destination.name;
        document.getElementById('detail-description').textContent = destination.description;
        document.getElementById('detail-teleport').href = destination.slurl;
        document.getElementById('detail-teleport').onclick = (e) => {
            e.preventDefault();
            teleport(destination.slurl);
        };
        
        // Show detail view
        document.getElementById('DetailView').classList.add('active');
    }

    function closeDetail() {
        document.getElementById('DetailView').classList.remove('active');
    }

    function teleport(slurl) {
        // Open the SLURL - this will trigger Second Life or compatible viewer
        window.open(slurl, '_self');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        initializeGuide();
    });

    // Handle escape key to close detail view
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('DetailView').classList.contains('active')) {
            closeDetail();
        }
    });
    </script>
    <?php
    return ob_get_clean();
}


public function add_clean_endpoint() {
    add_rewrite_rule('^destination-guide/?$', 'index.php?destination_guide_clean=1', 'top');
    add_rewrite_tag('%destination_guide_clean%', '([^&]+)');
}

public function handle_clean_endpoint() {
    if (get_query_var('destination_guide_clean')) {
        // Set headers for viewer compatibility
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Get destinations
        global $wpdb;
        $destinations = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY category, name");
        
        // Output clean HTML
        $this->output_clean_html($destinations);
        exit;
    }
}

private function output_clean_html($destinations) {
    $categories = empty($destinations) ? array() : $this->group_destinations_by_category($destinations);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'destination-guide-horizontal.css'; ?>" type="text/css">
        <title>Destination Guide</title>
        <style>
            body {
                margin: 0 !important;
                padding: 0 !important;
                background-color: #2b2b2b !important;
                overflow-y: hidden !important;
                font-family: "DejaVu Sans", Arial, sans-serif !important;
            }
            #DestinationGuide {
                margin: 0 !important;
                padding: 0 !important;
            }
        </style>
    </head>
    <body>
        <div id="DestinationGuide">
            <div id="AboutIcon">
                <a href="#" onclick="toggleDescription(); return false;">?</a>
            </div>
            
            <div id="Description">
                Welcome to the Destination Guide. Browse through our categories and click on any destination to teleport directly there!
            </div>
            
            <!-- Breadcrumb Navigation -->
            <div id="BreadcrumbNav">
                <a href="#" onclick="showCategories(); return false;" id="home-link">Categories</a>
                <span class="separator" id="breadcrumb-sep" style="display: none;">></span>
                <span id="current-category" style="display: none;"></span>
            </div>
            
            <!-- Main Content Area -->
            <div id="MainContent">
                <!-- Category View (Main Menu) -->
                <div id="CategoryView">
                    <!-- Categories will be populated here -->
                </div>
                
                <!-- Destination View (Category Contents) -->
                <div id="DestinationView" class="hidden">
                    <!-- Destinations will be populated here -->
                </div>
            </div>
            
            <!-- Detail View (Destination Details) -->
            <div id="DetailView">
                <button class="close-detail" onclick="closeDetail()">&times;</button>
                <div class="detail-content">
                    <img id="detail-image" class="detail-image" src="" alt="">
                    <div class="detail-info">
                        <div id="detail-title" class="detail-title"></div>
                        <div id="detail-description" class="detail-description"></div>
                        <a id="detail-teleport" class="detail-teleport" href="#">TELEPORT</a>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Destination data from WordPress
        const destinationData = <?php echo json_encode($categories); ?>;
        
        let currentView = 'categories';
        let currentCategory = '';

        function toggleDescription() {
            const desc = document.getElementById('Description');
            desc.style.display = desc.style.display === 'none' ? 'block' : 'none';
        }

        function initializeGuide() {
            showCategories();
        }

        function showCategories() {
            currentView = 'categories';
            currentCategory = '';
            
            // Update breadcrumb
            document.getElementById('breadcrumb-sep').style.display = 'none';
            document.getElementById('current-category').style.display = 'none';
            
            // Hide other views
            document.getElementById('DestinationView').classList.add('hidden');
            document.getElementById('DetailView').classList.remove('active');
            document.getElementById('CategoryView').classList.remove('hidden');
            
            // Populate categories
            const categoryView = document.getElementById('CategoryView');
            categoryView.innerHTML = '';
            
            if (Object.keys(destinationData).length === 0) {
                categoryView.innerHTML = '<div class="destination-guide-empty">No destinations available yet.</div>';
                return;
            }
            
            for (const [categoryName, destinations] of Object.entries(destinationData)) {
                const categoryTile = document.createElement('div');
                categoryTile.className = 'category-tile';
                categoryTile.onclick = () => showCategory(categoryName);
                
                // Use first destination image as category representative
                const representativeImage = destinations[0]?.image || '';
                
                categoryTile.innerHTML = `
                    ${representativeImage ? 
                        `<img src="${representativeImage}" alt="${escapeHtml(categoryName)}">` : 
                        `<div class="no-image-placeholder">No Image</div>`
                    }
                    <div class="category-name">
                        ${escapeHtml(categoryName)}
                        <div class="category-count">(${destinations.length} ${destinations.length === 1 ? 'item' : 'items'})</div>
                    </div>
                `;
                
                categoryView.appendChild(categoryTile);
            }
        }

        function showCategory(categoryName) {
            currentView = 'destinations';
            currentCategory = categoryName;
            
            // Update breadcrumb
            document.getElementById('breadcrumb-sep').style.display = 'inline';
            document.getElementById('current-category').style.display = 'inline';
            document.getElementById('current-category').textContent = categoryName;
            
            // Hide other views
            document.getElementById('CategoryView').classList.add('hidden');
            document.getElementById('DetailView').classList.remove('active');
            document.getElementById('DestinationView').classList.remove('hidden');
            
            // Populate destinations
            const destinationView = document.getElementById('DestinationView');
            destinationView.innerHTML = '';
            
            const destinations = destinationData[categoryName] || [];
            
            if (destinations.length === 0) {
                destinationView.innerHTML = '<div class="destination-guide-empty">No destinations in this category yet.</div>';
                return;
            }
            
            destinations.forEach(destination => {
                const destTile = document.createElement('div');
                destTile.className = 'destination-tile';
                destTile.onclick = (e) => {
                    // Prevent detail view when clicking teleport button
                    if (!e.target.classList.contains('TPButton')) {
                        showDestinationDetail(destination);
                    }
                };
                
                destTile.innerHTML = `
                    ${destination.image ? 
                        `<img src="${destination.image}" alt="${escapeHtml(destination.name)}">` : 
                        `<div class="no-image-placeholder">No Image</div>`
                    }
                    <div class="TPButton" onclick="teleport('${destination.slurl}'); event.stopPropagation();">VISIT</div>
                    <div class="destination-name">${escapeHtml(destination.name)}</div>
                `;
                
                destinationView.appendChild(destTile);
            });
        }

        function showDestinationDetail(destination) {
            // Update detail view content
            document.getElementById('detail-image').src = destination.image || '';
            document.getElementById('detail-image').style.display = destination.image ? 'block' : 'none';
            document.getElementById('detail-title').textContent = destination.name;
            document.getElementById('detail-description').textContent = destination.description;
            document.getElementById('detail-teleport').href = destination.slurl;
            document.getElementById('detail-teleport').onclick = (e) => {
                e.preventDefault();
                teleport(destination.slurl);
            };
            
            // Show detail view
            document.getElementById('DetailView').classList.add('active');
        }

        function closeDetail() {
            document.getElementById('DetailView').classList.remove('active');
        }

        function teleport(slurl) {
            // Open the SLURL - this will trigger Second Life or compatible viewer
            window.open(slurl, '_self');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeGuide();
        });

        // Handle escape key to close detail view
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('DetailView').classList.contains('active')) {
                closeDetail();
            }
        });
        </script>
    </body>
    </html>
    <?php
}

}

// Initialize the plugin
new DestinationGuide();
?>