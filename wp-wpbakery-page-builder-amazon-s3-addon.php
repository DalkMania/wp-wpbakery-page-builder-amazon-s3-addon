<?php
/*
Plugin Name: WPBakery Page Builder Amazon S3 Addon
Description: This plugin is an addon for WPBakery Page Builder. Useful when you happen to store your WP Media Library in an Amazon S3 Bucket and use the page builder's custom css functions. It will rewrite the URLs in the Inline CSS Blocks to reference the media in the S3 Bucket instead of locally.
Plugin URI: https://www.niklasdahlqvist.com
Author: Niklas Dahlqvist
Author URI: https://www.niklasdahlqvist.com
Version: 1.0.0
Requires at least: 4.9
License: GPL
*/

/**
* Ensure class doesn't already exist
*/

if (!class_exists('WPB_AWS_S3_ADDON')) {
    class WPB_AWS_S3_ADDON
    {
        public function __construct()
        {
            //Actions
            add_action('admin_notices', [$this, 'is_wp_offload_s3_installed'], 10);
            add_action('admin_notices', [$this, 'is_wpbakery_installed'], 10);
            add_action('init', [$this, 'remove_wpbakery_actions'], 10);
        }

        public function is_wp_offload_s3_installed()
        {
            global $pagenow, $page;
            $message = '';

            if ($pagenow != 'plugins.php') {
                return;
            }

            if (!is_plugin_active('amazon-s3-and-cloudfront/wordpress-s3.php')) {
                if (file_exists(WP_PLUGIN_DIR . '/amazon-s3-and-cloudfront/wordpress-s3.php')) {
                    $message .= '<p>WP Offload Media Lite is installed but not active. <strong>Activate WP Offload Media Lite</strong> to use this plugin.</p>';
                } else {
                    $message .= '<h2><a href="https://wordpress.org/plugins/amazon-s3-and-cloudfront/">WP Offload Media Lite</a> is required.</h2><p>You do not have the WP Offload Media Lite plugin enabled. <a href="https://wordpress.org/plugins/amazon-s3-and-cloudfront/">Get WP Offload Media Lite</a>.</p>';
                }
            }
            if (!empty($message)) {
                echo '<div id="message" class="error">' . $message . '</div>';
            }
        }

        public function is_wpbakery_installed() {
            global $pagenow, $page;
            $message = '';

            if ($pagenow != 'plugins.php') {
                return;
            }

            if( defined( 'WPB_VC_VERSION' ) ) {
                // Plugin is installed and acrive
                return;
            } else {
                $message .= '<h2><a href="https://wpbakery.com/">WPBakery Page Builder</a> is required.</h2><p>You do not have the WPBakery Page Builder plugin enabled. <a href="https://wpbakery.com/">Get WPBakery Page Builder</a>.</p>';
            }

            if (!empty($message)) {
                echo '<div id="message" class="error">' . $message . '</div>';
            }

        }

        public function remove_wpbakery_actions()
        {   
            if( defined( 'WPB_VC_VERSION' ) ) {
                remove_action('wp_head', array(visual_composer(), 'addFrontCss'), 1000);
                add_action('wp_head', array($this, 'addS3FrontCss'), 1000);
            }
        }

        public function addS3FrontCss()
        {
            $this->addS3PageCustomCss();
            $this->addS3ShortcodesCustomCss();
        }

        public function getProtocol()
        {
            // checking $protocol in HTTP or HTTPS
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                // this is HTTPS
                $protocol = 'https';
            } else {
                // this is HTTP
                $protocol = 'http';
            }
            return $protocol;
        }

        //change path to point to S3 Bucket
        public function get_s3_url()
        {
            $bucket_info = get_option('tantan_wordpress_s3');
            $as3cf_is_active = is_plugin_active('amazon-s3-and-cloudfront/wordpress-s3.php');

            if (!empty($bucket_info['bucket']) && $as3cf_is_active) {
                if ($bucket_info['domain'] == 'subdomain') {
                    $domain = $bucket_info['bucket'] . '.s3.amazonaws.com';
                } elseif ($bucket_info['domain'] == 'path') {
                    $domain = 's3.amazonaws.com/' . $bucket_info['bucket'];
                } elseif ($bucket_info['domain'] == 'cloudfront') {
                    $domain = $bucket_info['cloudfront'];
                }

                $protocol = $this->getProtocol();

                $url = $protocol . '://' . $domain . '/wp-content';
            }

            return $url;
        }

        public function addS3PageCustomCss($id = null)
        {
            if (is_front_page() || is_home()) {
                $id = get_queried_object_id();
            } elseif (is_singular()) {
                if (!$id) {
                    $id = get_the_ID();
                }
            }

            if ($id) {
                $post_custom_css = get_post_meta($id, '_wpb_post_custom_css', true);
                if (!empty($post_custom_css)) {
                    $post_custom_css = strip_tags($post_custom_css);
                    $url = $this->get_s3_url();
                    $post_custom_css = str_replace(WP_CONTENT_URL, $url, $post_custom_css);
                    echo '<style type="text/css" data-type="vc_custom-css">';
                    echo $post_custom_css;
                    echo '</style>';
                }
            }
        }

        public function addS3ShortcodesCustomCss($id = null)
        {
            if (!is_singular()) {
                return;
            }
            if (!$id) {
                $id = get_the_ID();
            }
            if ($id) {
                $shortcodes_custom_css = get_post_meta($id, '_wpb_shortcodes_custom_css', true);
                if (!empty($shortcodes_custom_css)) {
                    $shortcodes_custom_css = strip_tags($shortcodes_custom_css);
                    $url = $this->get_s3_url();
                    $shortcodes_custom_css = str_replace(WP_CONTENT_URL, $url, $shortcodes_custom_css);

                    echo '<style type="text/css" data-type="vc_shortcodes-custom-css">';
                    echo $shortcodes_custom_css;
                    echo '</style>';
                }
            }
        }

    } //End Class
    /**
     * Instantiate this class to ensure the action and shortcode hooks are hooked.
     * This instantiation can only be done once (see it's __construct() to understand why.)
     */

    add_action('plugins_loaded', function () {
        new WPB_AWS_S3_ADDON();
    });
} // End if class exists statement
