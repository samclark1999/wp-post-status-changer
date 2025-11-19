<div class="wrap">
    <h1><?php _e('Post Status Changer', 'post-status-changer'); ?></h1>

    <div class="psc-container">
        <div class="psc-form-container">
            <div class="psc-alert psc-alert-warning">
                <p><strong><?php _e('Warning:', 'post-status-changer'); ?></strong></p>
                <p><?php _e('Changing post statuses in bulk can have significant impacts:', 'post-status-changer'); ?></p>
                <ul>
                    <li><?php _e('Posts may become visible or hidden from your site visitors', 'post-status-changer'); ?></li>
                    <li><?php _e('SEO rankings could be affected if published content is unpublished', 'post-status-changer'); ?></li>
                    <li><?php _e('Scheduled posts may publish immediately if changed to "published" status', 'post-status-changer'); ?></li>
                    <li><?php _e('Large batches may impact database performance', 'post-status-changer'); ?></li>
                </ul>
                <p><?php _e('Consider using the "Dry Run" option first to preview changes.', 'post-status-changer'); ?></p>
            </div>

            <form id="psc-form" method="post" enctype="multipart/form-data">
                <div class="psc-form-group">
                    <label for="post_type"><?php _e('Select Post Type:', 'post-status-changer'); ?></label>
                    <select id="post_type" name="post_type" required>
                        <option value=""><?php _e('-- Select Post Type --', 'post-status-changer'); ?></option>
                        <?php foreach ($post_types as $type => $type_object) : ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type_object->labels->singular_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="psc-form-group">
                    <label><?php _e('Post Selection Mode:', 'post-status-changer'); ?></label>
                    <select id="psc_mode" name="psc_mode">
                        <option value="csv">CSV upload</option>
                        <option value="regex">Regex/Post Search</option>
                    </select>
                </div>

                <div class="psc-form-group" id="csv-upload-group">
                    <label for="csv_file"><?php _e('Upload CSV File (with post slugs):', 'post-status-changer'); ?></label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv">
                    <p class="description">
                        <?php _e('CSV should contain one column with post slugs.', 'post-status-changer'); ?>
                    </p>
                    <p class="description">
                        <?php printf(
                            __('Recommended batch size: %d posts for optimal performance.', 'post-status-changer'),
                            $recommended_batch
                        ); ?>
                    </p>
                </div>

                <div class="psc-form-group" id="regex-group" style="display:none;">
                    <label for="psc_regex_field">Field to Search:</label>
                    <select id="psc_regex_field" name="psc_regex_field" style="margin-bottom: 10px;">
                        <option value="post_title">Title</option>
                        <option value="post_name">Slug</option>
                        <option value="post_content">Content</option>
                        <option value="post_excerpt">Excerpt</option>
                    </select>
                    <div style="margin-bottom: 10px; color: #666; font-size: 13px;">
                        Choose which field to search for your pattern (e.g., Title, Slug, Content, or Excerpt). The regex will be applied to the selected field.
                    </div>
                    <label for="psc_regex">Regex/Post Search:</label>
                    <input type="text" id="psc_regex" name="psc_regex" placeholder="e.g. test|demo">
                    <p class="description">Enter a regex pattern to match posts by the selected field.</p>
                </div>

                <div class="psc-form-group">
                    <label for="psc_action"><?php _e('Post Actions:', 'post-status-changer'); ?></label>
                    <select id="psc_action" name="psc_action" required>
                        <option value="change_status"><?php _e('Change post status', 'post-status-changer'); ?></option>
                        <option value="trash"><?php _e('Move posts to trash', 'post-status-changer'); ?></option>
                    </select>
                </div>

                <div id="media-trash-warning" style="display:none; margin-top: 8px; margin-bottom: 15px; padding: 15px; border: 2px solid #e2b100; background: #fffbe5; color: #856404; font-weight: bold; border-radius: 5px; align-items: center;">
                    <span style="font-size: 20px; margin-right: 10px; vertical-align: middle;">&#9888;&#65039;</span>
                    Warning: Deleting media files is <strong>permanent</strong> and will delete the media file from the database. Proceed with caution!
                </div>

                <div class="psc-form-group" id="status-group">
                    <label for="status"><?php _e('Change Status To:', 'post-status-changer'); ?></label>
                    <select id="status" name="status">
                        <option value=""><?php _e('-- Select Status --', 'post-status-changer'); ?></option>
                        <?php foreach ($statuses as $status_key => $status_label) : ?>
                            <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="psc-form-group">
                    <label>
                        <input type="checkbox" id="dry_run" name="dry_run" value="true">
                        <?php _e('Dry Run (simulate changes without applying them)', 'post-status-changer'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Use this option to preview what would change without actually updating the database.', 'post-status-changer'); ?>
                    </p>
                </div>

                <div class="psc-form-group">
                    <label for="batch_size"><?php _e('Advanced: Override batch size limit', 'post-status-changer'); ?></label>
                    <input type="number" id="batch_size" name="batch_size" min="50" max="5000"
                        value="<?php echo esc_attr($recommended_batch); ?>">
                    <p class="description">
                        <?php _e('Default recommendation based on your server configuration. Increase with caution.', 'post-status-changer'); ?>
                    </p>
                </div>

                <div class="psc-form-group">
                    <button type="submit" id="psc-submit" class="button button-primary">
                        <?php _e('Process', 'post-status-changer'); ?>
                    </button>
                    <span id="psc-spinner" class="spinner"></span>
                </div>
            </form>
        </div>

        <div id="psc-results" class="psc-results-container" style="display: none;">
            <h2><?php _e('Results', 'post-status-changer'); ?></h2>
            <div id="psc-results-content"></div>
        </div>
    </div>

    <div class="psc-help-container">
        <h2><?php _e('About This Plugin', 'post-status-changer'); ?></h2>
        <p><?php _e('This plugin allows you to change the status of multiple posts at once using a CSV file containing post slugs.', 'post-status-changer'); ?></p>

        <h3><?php _e('Database Impact', 'post-status-changer'); ?></h3>
        <p><?php _e('When changing post statuses:', 'post-status-changer'); ?></p>
        <ul>
            <li><?php _e('The plugin updates the post_status field in the wp_posts table', 'post-status-changer'); ?></li>
            <li><?php _e('For password-protected posts, it also updates the post_password field', 'post-status-changer'); ?></li>
            <li><?php _e('WordPress triggers various hooks that may cause additional database operations by other plugins', 'post-status-changer'); ?></li>
        </ul>

        <h3><?php _e('Performance Considerations', 'post-status-changer'); ?></h3>
        <ul>
            <li><?php _e('For very large sites, consider processing posts in smaller batches', 'post-status-changer'); ?></li>
            <li><?php _e('Run during low-traffic periods to minimize impact on site performance', 'post-status-changer'); ?></li>
            <li><?php _e('Consider creating a database backup before making large-scale changes', 'post-status-changer'); ?></li>
        </ul>
    </div>
</div>