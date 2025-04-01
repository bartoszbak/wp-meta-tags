<?php
/**
 * Plugin Name: Meta fields
 * Description: Adds meta title, description, and image fields to posts and pages with Open Graph & Twitter Cards support.
 * Version: 1.0.0
 * Author: BB
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

class Meta_Fields_Manager {
    private $meta_update_count = array();
    private $max_updates_per_minute = 30;

    public function __construct() {
        add_action('init', array($this, 'register_meta'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        add_action('wp_head', array($this, 'output_meta_tags'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_meta() {
        register_post_meta('', '_meta_title', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => array($this, 'check_edit_permission'),
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_post_meta('', '_meta_description', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => array($this, 'check_edit_permission'),
            'sanitize_callback' => 'sanitize_textarea_field'
        ));

        register_post_meta('', '_meta_image', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'auth_callback' => array($this, 'check_edit_permission'),
            'sanitize_callback' => 'absint'
        ));
    }

    public function check_edit_permission() {
        if (!current_user_can('edit_posts')) {
            return false;
        }

        // Rate limiting check
        $user_id = get_current_user_id();
        $current_time = time();
        
        if (!isset($this->meta_update_count[$user_id])) {
            $this->meta_update_count[$user_id] = array(
                'count' => 0,
                'timestamp' => $current_time
            );
        }

        // Reset counter if a minute has passed
        if ($current_time - $this->meta_update_count[$user_id]['timestamp'] > 60) {
            $this->meta_update_count[$user_id] = array(
                'count' => 0,
                'timestamp' => $current_time
            );
        }

        // Check if user has exceeded the rate limit
        if ($this->meta_update_count[$user_id]['count'] >= $this->max_updates_per_minute) {
            return false;
        }

        $this->meta_update_count[$user_id]['count']++;
        return true;
    }

    public function register_rest_routes() {
        register_rest_route('meta-fields-manager/v1', '/validate-image', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_image_url'),
            'permission_callback' => array($this, 'check_edit_permission'),
            'args' => array(
                'url' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw'
                )
            )
        ));
    }

    public function validate_image_url($request) {
        $url = $request->get_param('url');
        
        // Handle relative URLs from WordPress media library
        if (strpos($url, 'http') !== 0) {
            $url = site_url($url);
        }
        
        // Check if URL is from allowed domains
        $allowed_domains = array(
            parse_url(get_site_url(), PHP_URL_HOST),
            'wordpress.com'
        );
        
        $url_host = parse_url($url, PHP_URL_HOST);
        if (!in_array($url_host, $allowed_domains)) {
            return new WP_Error(
                'invalid_domain',
                __('Image URL must be from an allowed domain.', 'meta-fields-manager'),
                array('status' => 400)
            );
        }

        // Verify image exists and is accessible
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => false // Add this for local development
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'image_not_found',
                __('Image not found or not accessible.', 'meta-fields-manager'),
                array('status' => 404)
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error(
                'image_not_found',
                __('Image not found or not accessible.', 'meta-fields-manager'),
                array('status' => $response_code)
            );
        }

        // Get content type and check if it's an image
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $content_type = strtolower($content_type);
        
        // List of common image content types
        $valid_image_types = array(
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/x-icon'
        );

        // Check if content type is in our list of valid image types
        if (!in_array($content_type, $valid_image_types)) {
            // If content type is not in our list, try to get the file extension
            $file_extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
            $valid_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico');
            
            if (!in_array($file_extension, $valid_extensions)) {
                return new WP_Error(
                    'invalid_image_type',
                    __('URL does not point to a valid image.', 'meta-fields-manager'),
                    array('status' => 400)
                );
            }
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Image URL is valid.', 'meta-fields-manager')
        ));
    }

    public function enqueue_editor_assets() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $asset_file = include(plugin_dir_path(__FILE__) . 'build/index.asset.php');

        wp_enqueue_script(
            'meta-fields-manager',
            plugins_url('build/index.js', __FILE__),
            $asset_file['dependencies'],
            $asset_file['version']
        );

        wp_enqueue_style(
            'meta-fields-manager',
            plugins_url('build/style-style.css', __FILE__),
            array('wp-components'),
            $asset_file['version']
        );

        // Add nonce for REST API
        wp_localize_script('meta-fields-manager', 'metaFieldsManager', array(
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('meta-fields-manager/v1/')
        ));
    }

    public function output_meta_tags() {
        if (!is_singular() || !current_user_can('read')) {
            return;
        }

        $post_id = get_the_ID();
        $meta_title = get_post_meta($post_id, '_meta_title', true);
        $meta_description = get_post_meta($post_id, '_meta_description', true);
        $meta_image_id = get_post_meta($post_id, '_meta_image', true);

        // If no custom meta title, use post title
        if (empty($meta_title)) {
            $meta_title = get_the_title();
        }

        // If no custom meta description, use excerpt
        if (empty($meta_description)) {
            $meta_description = get_the_excerpt();
        }

        // Get image URL from ID
        $meta_image = '';
        if (!empty($meta_image_id)) {
            $meta_image = wp_get_attachment_url($meta_image_id);
        } elseif (has_post_thumbnail($post_id)) {
            $meta_image = get_the_post_thumbnail_url($post_id, 'full');
        }

        // Basic meta tags
        echo '<meta name="title" content="' . esc_attr($meta_title) . '">' . "\n";
        echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";

        // Open Graph tags
        echo '<meta property="og:title" content="' . esc_attr($meta_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . "\n";
        
        if ($meta_image) {
            echo '<meta property="og:image" content="' . esc_url($meta_image) . '">' . "\n";
        }

        // Twitter Card tags
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($meta_title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($meta_description) . '">' . "\n";
        
        if ($meta_image) {
            echo '<meta name="twitter:image" content="' . esc_url($meta_image) . '">' . "\n";
        }
    }
}

new Meta_Fields_Manager(); 