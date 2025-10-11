<?php
/**
 * Destination form handler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Process the destination form submission
 */
function process_destination_form($id = null) {
    $is_edit = $id !== null;
    
    // Collect form data
    $data = array(
        'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
        'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
        'grid' => isset($_POST['grid']) ? sanitize_text_field($_POST['grid']) : '',
        'category' => isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '',
        'region' => isset($_POST['region']) ? sanitize_text_field($_POST['region']) : '',
        'teleport_url' => isset($_POST['teleport_url']) ? esc_url_raw($_POST['teleport_url']) : '',
        'x_coord' => isset($_POST['x_coord']) ? intval($_POST['x_coord']) : 0,
        'y_coord' => isset($_POST['y_coord']) ? intval($_POST['y_coord']) : 0,
        'z_coord' => isset($_POST['z_coord']) ? intval($_POST['z_coord']) : 0,
        'featured' => isset($_POST['featured']) ? 1 : 0
    );
    
    // Validate required fields
    $required_fields = array('name', 'grid', 'region');
    $errors = array();
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst($field) . ' is required.';
        }
    }
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            add_settings_error(
                'destination_guide',
                'required_field',
                $error,
                'error'
            );
        }
        return false;
    }
    
    // Handle file upload if provided
    $file = isset($_FILES['image']) ? $_FILES['image'] : null;
    
    // Initialize destination guide class
    $destination_guide = new Destination_Guide();
    
    // Add or update destination
    if ($is_edit) {
        $result = $destination_guide->update_destination($id, $data, $file);
        $message = 'Destination updated successfully.';
        $error = 'Error updating destination.';
    } else {
        $result = $destination_guide->create_destination($data, $file);
        $message = 'Destination added successfully.';
        $error = 'Error adding destination.';
    }
    
    if ($result) {
        add_settings_error(
            'destination_guide',
            'destination_updated',
            $message,
            'updated'
        );
        
        if (!$is_edit) {
            // Redirect to the edit page for the new destination
            wp_redirect(add_query_arg(array('action' => 'edit', 'id' => $result), admin_url('admin.php?page=destination-guide')));
            exit;
        }
    } else {
        add_settings_error(
            'destination_guide',
            'destination_error',
            $error,
            'error'
        );
    }
}

/**
 * Render the destination form
 */
function destination_guide_form($destination = null) {
    $is_edit = $destination !== null;
    
    // Get existing grids and categories for the dropdown
    $destination_guide = new Destination_Guide();
    $grids = $destination_guide->get_grids();
    $categories = $destination_guide->get_categories();
    
    // Set default values
    $values = array(
        'name' => '',
        'description' => '',
        'grid' => '',
        'category' => '',
        'region' => '',
        'image' => '',
        'teleport_url' => '',
        'x_coord' => 128,
        'y_coord' => 128,
        'z_coord' => 30,
        'featured' => 0
    );
    
    if ($is_edit) {
        $values = array_merge($values, $destination);
    }
    ?>
    <form method="post" enctype="multipart/form-data">
        <?php 
        if ($is_edit) {
            wp_nonce_field('destination_guide_edit_' . $destination['id'], 'destination_guide_nonce');
        } else {
            wp_nonce_field('destination_guide_add', 'destination_guide_nonce');
        }
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="name">Name*</label></th>
                <td>
                    <input type="text" name="name" id="name" value="<?php echo esc_attr($values['name']); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="description">Description</label></th>
                <td>
                    <textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea($values['description']); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="grid">Grid*</label></th>
                <td>
                    <input type="text" name="grid" id="grid" value="<?php echo esc_attr($values['grid']); ?>" class="regular-text" list="grid-list" required>
                    <datalist id="grid-list">
                        <?php foreach ($grids as $grid) : ?>
                            <option value="<?php echo esc_attr($grid); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="category">Category</label></th>
                <td>
                    <input type="text" name="category" id="category" value="<?php echo esc_attr($values['category']); ?>" class="regular-text" list="category-list">
                    <datalist id="category-list">
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr($category); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="region">Region*</label></th>
                <td>
                    <input type="text" name="region" id="region" value="<?php echo esc_attr($values['region']); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="image">Image</label></th>
                <td>
                    <?php if (!empty($values['image'])) : ?>
                        <div class="destination-image-preview">
                            <img src="<?php echo esc_url($values['image']); ?>" alt="Destination Image" style="max-width: 300px; max-height: 200px;">
                        </div>
                    <?php endif; ?>

                    <input type="file" name="image" id="image" accept="image/*">
                    <p class="description">Select an image for this destination. Leave empty to keep the existing image.</p>
                    
                    <input type="hidden" name="existing_image" value="<?php echo esc_attr($values['image']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="teleport_url">Teleport URL</label></th>
                <td>
                    <input type="text" name="teleport_url" id="teleport_url" value="<?php echo esc_url($values['teleport_url']); ?>" class="large-text">
                    <p class="description">Optional. Leave empty to generate automatically from region and coordinates.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Coordinates</th>
                <td>
                    <div class="coordinates-wrapper">
                        <label for="x_coord">X:</label>
                        <input type="number" name="x_coord" id="x_coord" value="<?php echo esc_attr($values['x_coord']); ?>" min="0" max="256" step="1">
                        
                        <label for="y_coord">Y:</label>
                        <input type="number" name="y_coord" id="y_coord" value="<?php echo esc_attr($values['y_coord']); ?>" min="0" max="256" step="1">
                        
                        <label for="z_coord">Z:</label>
                        <input type="number" name="z_coord" id="z_coord" value="<?php echo esc_attr($values['z_coord']); ?>" min="0" step="1">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">Featured</th>
                <td>
                    <label>
                        <input type="checkbox" name="featured" value="1" <?php checked($values['featured'], 1); ?>>
                        Mark as featured destination
                    </label>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="destination_guide_submit" class="button button-primary" value="<?php echo $is_edit ? 'Update Destination' : 'Add Destination'; ?>">
            <a href="<?php echo admin_url('admin.php?page=destination-guide'); ?>" class="button">Cancel</a>
        </p>
    </form>
    <?php
}
