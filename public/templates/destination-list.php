<?php
/**
 * Template for displaying the destination list
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get settings
$settings = get_option('destination_guide_settings', array(
    'items_per_page' => 12,
    'enable_grid_filter' => 1,
    'enable_category_filter' => 1
));

// Get available grids and categories for filtering
$grids = $destination_guide->get_grids();
$categories = $destination_guide->get_categories();

// Get current filter values
$current_grid = isset($_GET['grid']) ? sanitize_text_field($_GET['grid']) : 'all';
$current_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : 'all';

// Build page URL for filters
$page_url = remove_query_arg(array('grid', 'category'));
?>

<div class="destination-guide-container">
    <?php if (!empty($destinations)) : ?>
        <?php if ($settings['enable_grid_filter'] || $settings['enable_category_filter']) : ?>
            <div class="destination-guide-filters">
                <form method="get" action="<?php echo esc_url($page_url); ?>">
                    <?php 
                    // Keep any existing query parameters
                    foreach ($_GET as $key => $value) {
                        if ($key !== 'grid' && $key !== 'category') {
                            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                        }
                    }
                    ?>
                    
                    <?php if ($settings['enable_grid_filter'] && !empty($grids)) : ?>
                        <div class="filter-group">
                            <label for="grid-filter">Grid:</label>
                            <select id="grid-filter" name="grid" class="filter-select">
                                <option value="all" <?php selected($current_grid, 'all'); ?>>All Grids</option>
                                <?php foreach ($grids as $grid) : ?>
                                    <option value="<?php echo esc_attr($grid); ?>" <?php selected($current_grid, $grid); ?>>
                                        <?php echo esc_html($grid); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($settings['enable_category_filter'] && !empty($categories)) : ?>
                        <div class="filter-group">
                            <label for="category-filter">Category:</label>
                            <select id="category-filter" name="category" class="filter-select">
                                <option value="all" <?php selected($current_category, 'all'); ?>>All Categories</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category); ?>" <?php selected($current_category, $category); ?>>
                                        <?php echo esc_html($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <button type="submit" class="filter-button">Filter</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <div class="destination-guide-grid">
            <?php foreach ($destinations as $destination) : ?>
                <div class="destination-item <?php echo $destination['featured'] ? 'featured' : ''; ?>">
                    <div class="destination-image">
                        <?php if (!empty($destination['image'])) : ?>
                            <img src="<?php echo esc_url($destination['image']); ?>" alt="<?php echo esc_attr($destination['name']); ?>">
                        <?php else : ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                        
                        <?php if ($destination['featured']) : ?>
                            <span class="featured-badge">Featured</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="destination-details">
                        <h3 class="destination-name"><?php echo esc_html($destination['name']); ?></h3>
                        
                        <div class="destination-meta">
                            <span class="grid"><?php echo esc_html($destination['grid']); ?></span>
                            <span class="category"><?php echo esc_html($destination['category']); ?></span>
                        </div>
                        
                        <div class="destination-description">
                            <?php echo wpautop(wp_kses_post($destination['description'])); ?>
                        </div>
                        
                        <div class="destination-region">
                            <strong>Region:</strong> <?php echo esc_html($destination['region']); ?>
                        </div>
                        
                        <div class="destination-actions">
                            <a href="<?php echo esc_url($destination['teleport_url']); ?>" class="teleport-button" target="_blank">Teleport Now</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="destination-guide-empty">
            <p>No destinations found.</p>
        </div>
    <?php endif; ?>
</div>
