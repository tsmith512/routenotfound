<?php
/**
 * Plugin Name: RNF Archiver
 * Description: WordPress integration for RNF Archiver Cloudflare Worker
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Archive post when it's saved/published
 */
function rnf_archive_post_on_save($post_id) {
    $post = get_post($post_id);

    if ($post->post_status !== 'publish') {
        return;
    }

    $data = [
        'action' => 'create',
        'site_url' => get_site_url(),
        'post' => [
            'id' => $post->ID,
            'title' => ['rendered' => $post->post_title],
            'content' => ['rendered' => apply_filters('the_content', $post->post_content)],
            'link' => get_permalink($post->ID),
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            '_embedded' => [
                'wp:term' => [
                    get_the_terms($post->ID, 'category') ?: []
                ]
            ]
        ]
    ];

    wp_remote_post('https://rnf-archiver.tsmithcreative.workers.dev/archive', [
        'body' => json_encode($data),
        'headers' => [
            'Content-Type' => 'application/json',
            // 'Authorization' => 'Bearer ' . YOUR_API_KEY
        ]
    ]);
}
add_action('save_post', 'rnf_archive_post_on_save');

/**
 * Remove iframes when archive=true parameter is present to prevent networkidle issues
 */
function rnf_remove_iframes_for_archiving($content) {
    if (isset($_GET['archive']) && $_GET['archive'] === 'true') {
        // Remove all iframe tags from content
        $content = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $content);
        $content = preg_replace('/<iframe[^>]*\/?>/', '', $content);
        // Clean up extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
    }
    return $content;
}

// Apply filter to remove iframes for archiving
add_filter('the_content', 'rnf_remove_iframes_for_archiving', 999);
add_filter('the_excerpt', 'rnf_remove_iframes_for_archiving', 999);

/**
 * Disable lazy loading and analytics when archive=true parameter is present
 */
function rnf_optimize_for_archiving() {
    if (isset($_GET['archive']) && $_GET['archive'] === 'true') {
        // Disable WordPress lazy loading
        add_filter('wp_lazy_loading_enabled', '__return_false');

        // Remove analytics tracking code
        remove_action('wp_footer', 'rnf_analytics_ga_tracking_code_output', 1000);
    }
}
add_action('wp_enqueue_scripts', 'rnf_optimize_for_archiving');


// Temporary bulk archive function - REMOVE AFTER USE
function handle_bulk_archive_request() {
    if (!isset($_GET['bulk_archive']) || !current_user_can('manage_options')) {
        return;
    }

    $posts = get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC'
    ]);

    echo '<h1>Bulk Archive Progress</h1>';
    echo '<p>Found ' . count($posts) . ' posts to archive...</p>';

    foreach ($posts as $post) {
        $data = [
            'action' => 'create',
            'site_url' => get_site_url(),
            'post' => [
                'id' => $post->ID,
                'title' => ['rendered' => $post->post_title],
                'content' => ['rendered' => apply_filters('the_content', $post->post_content)],
                'link' => get_permalink($post->ID),
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'slug' => $post->post_name,
                'status' => $post->post_status,
                '_embedded' => [
                    'wp:term' => [
                        get_the_terms($post->ID, 'category') ?: []
                    ]
                ]
            ]
        ];

        $response = wp_remote_post('https://rnf-archiver.tsmithcreative.workers.dev/archive', [
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                // 'Authorization' => 'Bearer ' . YOUR_API_KEY
            ]
        ]);

        if (is_wp_error($response)) {
            echo '<p style="color: red;">Failed to queue post ' . $post->ID . ': ' . $response->get_error_message() . '</p>';
        } else {
            echo '<p style="color: green;">Queued post ' . $post->ID . ': ' . esc_html($post->post_title) . '</p>';
        }

        // Flush output and add delay
        if (ob_get_level()) ob_flush();
        flush();
        sleep(1);
    }

    echo '<h2>Bulk archive complete!</h2>';
    echo '<p><strong>Remember to remove this function from functions.php</strong></p>';
    exit;
}
// add_action('wp_loaded', 'handle_bulk_archive_request');
