<?php
/**
 * Main Destination Guide class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Destination_Guide {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Nothing to do here yet
    }
    
    /**
     * Get destinations based on parameters
     */
    public function get_destinations($args = array()) {
        return destination_guide_get_destinations($args);
    }
    
    /**
     * Get a single destination
     */
    public function get_destination($id) {
        return destination_guide_get_destination($id);
    }
    
    /**
     * Handle image upload and return the file URL
     */
    public function handle_image_upload($file) {
        // Check if upload directory exists, create if it doesn't
        if (!file_exists(DESTINATION_GUIDE_UPLOAD_DIR)) {
            wp_mkdir_p(DESTINATION_GUIDE_UPLOAD_DIR);
        }
        
        // Make sure we have a file
        if (!isset($file['name']) || empty($file['name'])) {
            return false;
        }
        
        // Setup WP upload overrides
        $upload_overrides = array(
            'test_form' => false,
            'test_size' => true,
            'test_upload' => true,
        );
        
        // Handle the upload using WordPress functions
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            return array(
                'success' => false,
                'error' => $uploaded_file['error']
            );
        }
        
        // If the upload was successful, return the URL
        if (isset($uploaded_file['file']) && isset($uploaded_file['url'])) {
            // Generate a unique filename
            $filename = wp_unique_filename(dirname($uploaded_file['file']), basename($uploaded_file['file']));
            $new_file = dirname($uploaded_file['file']) . '/' . $filename;
            
            // Move the file
            rename($uploaded_file['file'], $new_file);
            
            // Get the URL
            $url = str_replace(basename($uploaded_file['url']), $filename, $uploaded_file['url']);
            
            return array(
                'success' => true,
                'filename' => $filename,
                'url' => $url,
                'path' => $new_file
            );
        }
        
        return array(
            'success' => false,
            'error' => 'Unknown error uploading file'
        );
    }
    
    /**
     * Create a destination
     */
    public function create_destination($data, $file = null) {
        // Handle image upload if provided
        if ($file && !empty($file['name'])) {
            $upload_result = $this->handle_image_upload($file);
            
            if ($upload_result && isset($upload_result['success']) && $upload_result['success']) {
                $data['image'] = $upload_result['url'];
            }
        }
        
        // Insert the destination
        $result = destination_guide_insert_destination($data);
        
        return $result;
    }
    
    /**
     * Update a destination
     */
    public function update_destination($id, $data, $file = null) {
        // Get existing destination data
        $existing = $this->get_destination($id);
        
        if (!$existing) {
            return false;
        }
        
        // Handle image upload if provided
        if ($file && !empty($file['name'])) {
            $upload_result = $this->handle_image_upload($file);
            
            if ($upload_result && isset($upload_result['success']) && $upload_result['success']) {
                // Delete old image if exists and it's in our upload directory
                if (!empty($existing['image'])) {
                    $old_image_path = str_replace(DESTINATION_GUIDE_UPLOAD_URL, DESTINATION_GUIDE_UPLOAD_DIR, $existing['image']);
                    if (file_exists($old_image_path) && strpos($old_image_path, DESTINATION_GUIDE_UPLOAD_DIR) === 0) {
                        wp_delete_file($old_image_path);
                    }
                }
                
                $data['image'] = $upload_result['url'];
            }
        } else {
            // Keep existing image if no new one provided
            $data['image'] = $existing['image'];
        }
        
        // Update the destination
        $result = destination_guide_update_destination($id, $data);
        
        return $result;
    }
    
    /**
     * Delete a destination
     */
    public function delete_destination($id) {
        // Get existing destination data
        $existing = $this->get_destination($id);
        
        if (!$existing) {
            return false;
        }
        
        // Delete associated image if it's in our upload directory
        if (!empty($existing['image'])) {
            $image_path = str_replace(DESTINATION_GUIDE_UPLOAD_URL, DESTINATION_GUIDE_UPLOAD_DIR, $existing['image']);
            if (file_exists($image_path) && strpos($image_path, DESTINATION_GUIDE_UPLOAD_DIR) === 0) {
                wp_delete_file($image_path);
            }
        }
        
        // Delete the destination
        $result = destination_guide_delete_destination($id);
        
        return $result;
    }
    
    /**
     * Export destinations to CSV
     */
    public function export_to_csv($args = array()) {
        $destinations = $this->get_destinations($args);
        
        if (empty($destinations)) {
            return false;
        }
        
        // Define the CSV headers
        $headers = array(
            'Name',
            'Description',
            'Grid',
            'Category',
            'Region',
            'Image',
            'Teleport URL',
            'X Coordinate',
            'Y Coordinate',
            'Z Coordinate',
            'Featured'
        );
        
        // Create a file handle
        $output = fopen('php://output', 'w');
        
        // Write the headers
        fputcsv($output, $headers);
        
        // Write the data
        foreach ($destinations as $destination) {
            $row = array(
                $destination['name'],
                $destination['description'],
                $destination['grid'],
                $destination['category'],
                $destination['region'],
                $destination['image'],
                $destination['teleport_url'],
                $destination['x_coord'],
                $destination['y_coord'],
                $destination['z_coord'],
                $destination['featured']
            );
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        
        return true;
    }
    
    /**
     * Generate teleport URL based on grid and coordinates
     */
    public function generate_teleport_url($grid, $region, $x, $y, $z) {
        // This function should be customized based on your specific needs
        // This is a basic example that creates a secondlife:// URL
        return "secondlife://{$region}/{$x}/{$y}/{$z}";
    }
    
    /**
     * Get unique grid names
     */
    public function get_grids() {
        return destination_guide_get_grids();
    }
    
    /**
     * Get unique category names
     */
    public function get_categories() {
        return destination_guide_get_categories();
    }
}
