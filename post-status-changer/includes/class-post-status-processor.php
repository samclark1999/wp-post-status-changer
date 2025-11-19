<?php

/**
 * Processor class for handling CSV import and status changes
 */
class Post_Status_Processor
{

    /**
     * Process the CSV file and change post statuses
     *
     * @param array $file The uploaded CSV file
     * @param string $post_type The post type to change
     * @param string $status The new status
     * @param string $password Optional password for password-protected posts
     * @param bool $dry_run Whether to simulate changes without applying them
     * @param int $batch_size Optional batch size override (0 for default)
     * @return array|WP_Error Result of the operation
     */
    public function process_csv($file, $post_type, $status, $password = '', $dry_run = false, $batch_size = 0)
    {
        // Check if file is valid
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return new WP_Error('invalid_file', __('Invalid file uploaded.', 'post-status-changer'));
        }

        // Check file extension
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($file_ext !== 'csv') {
            return new WP_Error('invalid_extension', __('Please upload a CSV file.', 'post-status-changer'));
        }

        // Open the file
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return new WP_Error('file_open_error', __('Could not open the CSV file.', 'post-status-changer'));
        }

        $results = array(
            'total' => 0,
            'successful' => 0,
            'already_status' => 0,
            'failed' => 0,
            'failed_entries' => array(), // Store detailed failure info
            'would_change' => array(), // For dry run mode
            'batch_size' => $batch_size, // Store the batch size used
        );

        // Determine appropriate batch size if not specified
        if ($batch_size <= 0) {
            $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
            $memory_mb = $memory_limit / (1024 * 1024);
            $batch_size = min(1000, max(250, floor($memory_mb / 0.5)));

            // Round to a nice number
            if ($batch_size > 500) {
                $batch_size = floor($batch_size / 100) * 100;
            } else {
                $batch_size = floor($batch_size / 50) * 50;
            }

            $results['batch_size'] = $batch_size;
        }

        // Read all slugs from CSV
        $all_entries = array();
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (!empty($data[0])) {
                $all_entries[] = trim($data[0]);
            }
        }

        // Reset file pointer
        rewind($handle);

        // Process in batches
        $current_batch = 0;
        $processed_count = 0;
        $total_entries = count($all_entries);
        $results['total'] = $total_entries;

        while ($processed_count < $total_entries) {
            // Get current batch of entries
            $batch_entries = array_slice($all_entries, $processed_count, $batch_size);
            $current_batch++;

            // Process this batch
            $batch_results = $this->process_batch($batch_entries, $post_type, $status, $dry_run);

            // Merge results
            $results['successful'] += $batch_results['successful'];
            $results['already_status'] += $batch_results['already_status'];
            $results['failed'] += $batch_results['failed'];
            $results['failed_entries'] = array_merge($results['failed_entries'], $batch_results['failed_entries']);

            if ($dry_run) {
                $results['would_change'] = array_merge($results['would_change'], $batch_results['would_change']);
            }

            $processed_count += count($batch_entries);

            // Free up memory
            unset($batch_entries, $batch_results);

            // Give the server a tiny breather between large batches
            if ($batch_size > 500 && $current_batch % 5 === 0) {
                usleep(500000); // 0.5 second pause
            }
        }

        fclose($handle);

        return $results;
    }

    /**
     * Process a batch of entries
     *
     * @param array $entries Array of entries to process
     * @param string $post_type The post type to change
     * @param string $status The new status
     * @param bool $dry_run Whether to simulate changes without applying them
     * @return array Results for this batch
     */
    private function process_batch($entries, $post_type, $status, $dry_run)
    {
        $results = array(
            'successful' => 0,
            'already_status' => 0,
            'failed' => 0,
            'failed_entries' => array(),
            'would_change' => array(),
        );

        foreach ($entries as $entry) {
            $original_entry = $entry;
            $slug = $entry;
            $failure_reason = '';

            // Check if the entry is a URL instead of a slug
            if (filter_var($entry, FILTER_VALIDATE_URL) || strpos($entry, '/') !== false) {
                // Try to extract the slug from the URL
                $path = parse_url($entry, PHP_URL_PATH);
                if ($path) {
                    $path_parts = explode('/', trim($path, '/'));
                    $slug = end($path_parts);
                }

                // If still contains slashes, mark as failed
                if (strpos($slug, '/') !== false) {
                    $results['failed']++;
                    $results['failed_entries'][] = array(
                        'slug' => $original_entry,
                        'reason' => __('Could not extract post slug from URL', 'post-status-changer')
                    );
                    continue;
                }
            }

            // Find the post by slug and post type
            $args = array(
                'name' => $slug,
                'post_type' => $post_type,
                'post_status' => 'any',
                'posts_per_page' => 1
            );

            $post_query = new WP_Query($args);

            if (!$post_query->have_posts()) {
                $results['failed']++;
                $results['failed_entries'][] = array(
                    'slug' => $original_entry,
                    'reason' => sprintf(
                        __('No %s found with slug "%s"', 'post-status-changer'),
                        get_post_type_object($post_type)->labels->singular_name,
                        $slug
                    )
                );
                continue;
            }

            $post = $post_query->posts[0];

            // Check if the post already has the target status
            $current_status = $post->post_status;

            // Skip if already has the target status
            if ($status === $current_status) {
                $results['already_status']++;
                continue;
            }

            // In dry run mode, just record what would change
            if ($dry_run) {
                $results['would_change'][] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'slug' => $post->post_name,
                    'current_status' => $current_status,
                    'new_status' => $status
                );
                $results['successful']++;
                continue;
            }

            // In process_batch, if $status === 'trash', use wp_trash_post($post->ID) instead of wp_update_post.
            if ($status === 'trash') {
                wp_trash_post($post->ID);
            } else {
                // Prepare post data for update
                $post_data = array(
                    'ID' => $post->ID,
                    'post_status' => $status
                );

                // Update the post
                $updated = wp_update_post($post_data, true);

                if (is_wp_error($updated)) {
                    $results['failed']++;
                    $results['failed_entries'][] = array(
                        'slug' => $original_entry,
                        'reason' => $updated->get_error_message()
                    );
                } else {
                    $results['successful']++;
                }
            }
        }

        return $results;
    }

    public function process_posts($args)
    {
        $mode = isset($args['mode']) ? $args['mode'] : 'csv';
        $post_type = $args['post_type'];
        $status = $args['status'];
        $dry_run = isset($args['dry_run']) ? $args['dry_run'] : false;
        $batch_size = isset($args['batch_size']) ? $args['batch_size'] : 0;

        if ($mode === 'csv') {
            return $this->process_csv($args['csv_file'], $post_type, $status, '', $dry_run, $batch_size);
        }

        // Regex mode
        $regex = isset($args['regex']) ? $args['regex'] : '';
        $regex_field = isset($args['regex_field']) ? $args['regex_field'] : 'post_title';
        if (empty($regex)) {
            return new WP_Error('missing_regex', __('Regex/Post Search is required.', 'post-status-changer'));
        }

        // Query all posts of the given type
        $query_args = array(
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        $post_ids = get_posts($query_args);

        $matched_ids = array();
        foreach ($post_ids as $post_id) {
            $value = get_post_field($regex_field, $post_id);
            if ($value !== null && preg_match('/' . $regex . '/i', $value)) {
                $matched_ids[] = $post_id;
            }
        }
        $matched_ids = array_unique($matched_ids);

        // Convert IDs to slugs for process_batch
        $entries = array();
        foreach ($matched_ids as $post_id) {
            $post = get_post($post_id);
            if ($post) {
                $entries[] = $post->post_name;
            }
        }
        $entries = array_unique($entries);

        // Batch processing (reuse logic from process_csv)
        $results = array(
            'total' => count($entries),
            'successful' => 0,
            'already_status' => 0,
            'failed' => 0,
            'failed_entries' => array(),
            'would_change' => array(),
            'batch_size' => $batch_size,
        );

        if ($batch_size <= 0) {
            $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
            $memory_mb = $memory_limit / (1024 * 1024);
            $batch_size = min(1000, max(250, floor($memory_mb / 0.5)));
            if ($batch_size > 500) {
                $batch_size = floor($batch_size / 100) * 100;
            } else {
                $batch_size = floor($batch_size / 50) * 50;
            }
            $results['batch_size'] = $batch_size;
        }

        $current_batch = 0;
        $processed_count = 0;
        $total_entries = count($entries);
        $results['total'] = $total_entries;

        while ($processed_count < $total_entries) {
            $batch_entries = array_slice($entries, $processed_count, $batch_size);
            $current_batch++;
            $batch_results = $this->process_batch($batch_entries, $post_type, $status, $dry_run);
            $results['successful'] += $batch_results['successful'];
            $results['already_status'] += $batch_results['already_status'];
            $results['failed'] += $batch_results['failed'];
            $results['failed_entries'] = array_merge($results['failed_entries'], $batch_results['failed_entries']);
            if ($dry_run) {
                $results['would_change'] = array_merge($results['would_change'], $batch_results['would_change']);
            }
            $processed_count += count($batch_entries);
            unset($batch_entries, $batch_results);
            if ($batch_size > 500 && $current_batch % 5 === 0) {
                usleep(500000);
            }
        }
        return $results;
    }
}
