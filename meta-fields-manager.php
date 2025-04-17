<?php
/**
 * Plugin Name: SEO Fields
 * Description: Adds meta title, description, keywords, author, and image fields to posts and pages with Open Graph & social media card support.
 * Version: 1.0.0
 * Author: BB.CV
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
        add_action('wp_head', array($this, 'output_meta_tags'), 1);
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Add title filters
        add_filter('wp_title', array($this, 'filter_wp_title'), 10, 2);
        add_filter('document_title_parts', array($this, 'filter_document_title_parts'));
        add_filter('bloginfo', array($this, 'filter_bloginfo'), 10, 2);
        add_filter('bloginfo_rss', array($this, 'filter_bloginfo'), 10, 2);
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
        
        register_post_meta('', '_meta_keywords', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => array($this, 'check_edit_permission'),
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_post_meta('', '_meta_author', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => array($this, 'check_edit_permission'),
            'sanitize_callback' => 'sanitize_text_field'
        ));
    }

    public function filter_wp_title($title, $sep) {
        if (is_singular()) {
            $post_id = get_the_ID();
            $meta_title = get_post_meta($post_id, '_meta_title', true);
            if ($meta_title) {
                return $meta_title;
            }
        }
        return $title;
    }

    public function filter_document_title_parts($title_parts) {
        if (is_singular()) {
            $post_id = get_the_ID();
            $meta_title = get_post_meta($post_id, '_meta_title', true);
            if ($meta_title) {
                $title_parts['title'] = $meta_title;
                // Remove site name and tagline for cleaner title
                if (isset($title_parts['site'])) {
                    unset($title_parts['site']);
                }
                if (isset($title_parts['tagline'])) {
                    unset($title_parts['tagline']);
                }
            }
        }
        return $title_parts;
    }

    public function filter_bloginfo($output, $show) {
        if (is_singular() && 'name' === $show) {
            $post_id = get_the_ID();
            $meta_title = get_post_meta($post_id, '_meta_title', true);
            if ($meta_title) {
                return $meta_title;
            }
        }
        return $output;
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

        $asset_file_path = plugin_dir_path(__FILE__) . 'build/index.asset.php';
        if (!file_exists($asset_file_path)) {
            return;
        }

        $asset_file = include($asset_file_path);
        if (!is_array($asset_file) || !isset($asset_file['dependencies']) || !isset($asset_file['version'])) {
            return;
        }

        // Define core dependencies
        $core_dependencies = array(
            'wp-blocks',
            'wp-block-editor',
            'wp-components',
            'wp-data',
            'wp-element',
            'wp-i18n',
            'wp-plugins',
            'wp-server-side-render'
        );

        // Merge with any additional dependencies from the asset file
        $dependencies = array_unique(array_merge(
            $core_dependencies,
            array_diff($asset_file['dependencies'], ['wp-editor'])
        ));

        // Register and enqueue the script
        wp_register_script(
            'meta-fields-manager',
            plugins_url('build/index.js', __FILE__),
            $dependencies,
            $asset_file['version'],
            true
        );

        // Register and enqueue the style
        wp_register_style(
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

        // Enqueue the assets
        wp_enqueue_script('meta-fields-manager');
        wp_enqueue_style('meta-fields-manager');
    }

    public function output_meta_tags() {
        if (!is_singular()) {
            return;
        }

        $post_id = get_the_ID();
        $meta_title = get_post_meta($post_id, '_meta_title', true);
        $meta_description = get_post_meta($post_id, '_meta_description', true);
        $meta_image_id = get_post_meta($post_id, '_meta_image', true);
        $meta_keywords = get_post_meta($post_id, '_meta_keywords', true);
        $meta_author = get_post_meta($post_id, '_meta_author', true);

        // If no custom meta title, use post title
        if (empty($meta_title)) {
            $meta_title = get_the_title();
        }

        // If no custom meta description, use excerpt
        if (empty($meta_description)) {
            $meta_description = get_the_excerpt();
        }

        // If no custom meta author, use post author
        if (empty($meta_author)) {
            $meta_author = get_the_author();
        }

        // If no custom meta keywords, use post categories and tags
        if (empty($meta_keywords)) {
            $categories = get_the_category();
            $tags = get_the_tags();
            $keywords = array();
            
            if ($categories) {
                foreach ($categories as $category) {
                    $keywords[] = $category->name;
                }
            }
            
            if ($tags) {
                foreach ($tags as $tag) {
                    $keywords[] = $tag->name;
                }
            }
            
            $meta_keywords = implode(', ', $keywords);
        }

        // Get image URL from ID
        $meta_image = '';
        if (!empty($meta_image_id)) {
            $meta_image = wp_get_attachment_url($meta_image_id);
        } elseif (has_post_thumbnail($post_id)) {
            $meta_image = get_the_post_thumbnail_url($post_id, 'full');
        }

        // Get current URL
        $current_url = get_permalink();

        // Output meta tags
        echo "\n<!-- Primary Meta Tags -->\n";
        echo '<meta name="title" content="' . esc_attr($meta_title) . '" />' . "\n";
        echo '<meta name="description" content="' . esc_attr($meta_description) . '" />' . "\n";
        echo '<meta name="keywords" content="' . esc_attr($meta_keywords) . '" />' . "\n";
        echo '<meta name="author" content="' . esc_attr($meta_author) . '" />' . "\n";
        echo '<link rel="canonical" href="' . esc_url($current_url) . '" />' . "\n\n";

        echo "<!-- Open Graph / Facebook -->\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($current_url) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($meta_title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        if ($meta_image) {
            echo '<meta property="og:image" content="' . esc_url($meta_image) . '" />' . "\n";
            echo '<meta property="og:image:secure_url" content="' . esc_url($meta_image) . '" />' . "\n";
            echo '<meta property="og:image:width" content="1200" />' . "\n";
            echo '<meta property="og:image:height" content="630" />' . "\n";
        }
        echo "\n";

        echo "<!-- Twitter -->\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:url" content="' . esc_url($current_url) . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($meta_title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($meta_description) . '" />' . "\n";
        if ($meta_image) {
            echo '<meta name="twitter:image" content="' . esc_url($meta_image) . '" />' . "\n";
        }
        echo "\n";
    }
}

new Meta_Fields_Manager(); 