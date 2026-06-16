<?php

namespace TypesenseSearch\Admin;

/**
 * Class MetaBox
 *
 * Registers a "Typesense Search" meta box on every post type that has
 * indexing enabled in the plugin settings.
 *
 * For attachment posts (PDFs) the standard meta box cannot be used because
 * the media-library overlay is a Backbone.js app that ignores meta boxes
 * entirely.  Instead, attachment fields are registered via the WordPress
 * `attachment_fields_to_edit` filter so they appear in both the overlay
 * details panel and the full attachment edit page.  Saving is handled by
 * the `edit_attachment` action, which fires in both save paths (AJAX overlay
 * and regular form submit).
 *
 * Exposed meta keys:
 *   MetaBox::META_EXCLUDE             (_typesense_exclude)             — '1' = excluded from index
 *   MetaBox::META_EXCLUDE_AS_SECTION  (_typesense_exclude_as_section)  — '1' = not used as a section
 *   MetaBox::META_EXTRA_TERMS         (_typesense_extra_terms)         — additional search terms string
 *
 * @package TypesenseSearch\Admin
 */
class MetaBox
{
    public const META_EXCLUDE            = '_typesense_exclude';
    public const META_EXCLUDE_AS_SECTION = '_typesense_exclude_as_section';
    public const META_EXTRA_TERMS        = '_typesense_extra_terms';

    private const NONCE_ACTION = 'typesense_metabox_save';
    private const NONCE_FIELD  = '_typesense_metabox_nonce';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register']);
        add_action('save_post',      [$this, 'save'], 10, 2);

        // Attachment overlay & full-edit-page support.
        // attachment_fields_to_edit renders into both the media-library modal
        // and the full edit page; edit_attachment fires from both save paths.
        // Priority 5 ensures the meta is written before IndexingHooks::onEditAttachment
        // (priority 10) reads it to make the index/deindex decision.
        if ((bool) get_option(Settings::OPTION_INDEX_PDF, 0)) {
            add_filter('attachment_fields_to_edit', [$this, 'addAttachmentFields'], 10, 2);
            add_action('edit_attachment',           [$this, 'saveAttachmentFields'], 5);
        }
    }

    /**
     * Register the meta box for every currently-enabled post type.
     *
     * Note: attachments are handled separately via attachment_fields_to_edit
     * (see constructor) because the media-library overlay does not render
     * meta boxes.
     */
    public function register(): void
    {
        $enabledPostTypes = (array) get_option(Settings::OPTION_POST_TYPES, []);

        foreach ($enabledPostTypes as $postType) {
            add_meta_box(
                'typesense-search',
                __('Search settings', 'typesense-search'),
                [$this, 'render'],
                $postType,
                'side',
                'high'
            );
        }
    }

    /**
     * Render the meta box HTML.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $exclude          = get_post_meta($post->ID, self::META_EXCLUDE, true) === '1';
        $excludeAsSection = get_post_meta($post->ID, self::META_EXCLUDE_AS_SECTION, true) === '1';
        $extraTerms       = (string) get_post_meta($post->ID, self::META_EXTRA_TERMS, true);
        ?>

        <style>
        .ts-metabox { padding: 8px 0; }
        .ts-metabox__row { margin-bottom: 12px; }
        .ts-metabox__row--checkbox { display: flex; align-items: center; gap: 8px; padding: 6px 0; }
        .ts-metabox__checkbox-label { display: inline-block; margin-left: 4px; font-weight: 600; }
        .ts-metabox__label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .ts-metabox__textarea { margin-top: 4px; min-height: 72px; resize: vertical; }
        .ts-metabox__description { margin-top: 6px; color: #666; font-size: 12px; line-height: 1.3; }
        .ts-metabox__description--section { margin-bottom: 12px; }
        .ts-metabox input[type="checkbox"] { width: 16px; height: 16px; margin: 0; }
        </style>

        <div class="ts-metabox">

            <label class="ts-metabox__row ts-metabox__row--checkbox">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(self::META_EXCLUDE); ?>"
                    id="ts-exclude"
                    value="1"
                    <?php checked($exclude); ?>
                />
                <span class="ts-metabox__checkbox-label">
                    <?php esc_html_e('Exclude from search index', 'typesense-search'); ?>
                </span>
            </label>

            <?php if ($post->post_type === 'page') : ?>
                <label class="ts-metabox__row ts-metabox__row--checkbox">
                    <input
                        type="checkbox"
                        name="<?php echo esc_attr(self::META_EXCLUDE_AS_SECTION); ?>"
                        id="ts-exclude-as-section"
                        value="1"
                        <?php checked($excludeAsSection); ?>
                    />
                    <span class="ts-metabox__checkbox-label">
                        <?php esc_html_e('Exclude as search section', 'typesense-search'); ?>
                    </span>
                </label>
                <p class="ts-metabox__description ts-metabox__description--section">
                    <?php esc_html_e('Prevents this page from being used as the section label for itself and descendant pages when it is the top page in the page tree.', 'typesense-search'); ?>
                </p>
            <?php endif; ?>

            <div class="ts-metabox__row">
                <label for="ts-extra-terms" class="ts-metabox__label">
                    <?php esc_html_e('Extra search terms', 'typesense-search'); ?>
                </label>
                <textarea
                    id="ts-extra-terms"
                    name="<?php echo esc_attr(self::META_EXTRA_TERMS); ?>"
                    rows="3"
                    class="widefat ts-metabox__textarea"
                    placeholder="<?php esc_attr_e('Keywords, synonyms or alternate phrasings…', 'typesense-search'); ?>"
                ><?php echo esc_textarea($extraTerms); ?></textarea>
                <p class="ts-metabox__description">
                    <?php esc_html_e('Additional words or phrases that should match this post in search, even if they do not appear in the content.', 'typesense-search'); ?>
                </p>
            </div>

        </div>

        <?php
    }

    /**
     * Save meta box values when the post is saved.
     *
     * @param int      $postId Post ID.
     * @param \WP_Post $post   Post object.
     */
    public function save(int $postId, \WP_Post $post): void
    {
        // Verify nonce
        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD] ?? ''));
        if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        // Skip autosaves, revisions, and non-manage_options users
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($postId)) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // ── Exclude flag ──────────────────────────────────────────────────────
        $exclude = isset($_POST[self::META_EXCLUDE]) && $_POST[self::META_EXCLUDE] === '1' ? '1' : '0';
        update_post_meta($postId, self::META_EXCLUDE, $exclude);

        // ── Exclude as section flag (pages only) ───────────────────────────
        if ($post->post_type === 'page') {
            $previousExcludeAsSection = get_post_meta($postId, self::META_EXCLUDE_AS_SECTION, true) === '1';
            $excludeAsSection = isset($_POST[self::META_EXCLUDE_AS_SECTION]) && $_POST[self::META_EXCLUDE_AS_SECTION] === '1' ? '1' : '0';

            update_post_meta($postId, self::META_EXCLUDE_AS_SECTION, $excludeAsSection);

            if ($previousExcludeAsSection !== ($excludeAsSection === '1')) {
                do_action('typesense_search/section_exclusion_changed', $postId);
            }
        }

        // ── Extra search terms ────────────────────────────────────────────────
        $extraTerms = sanitize_textarea_field(wp_unslash($_POST[self::META_EXTRA_TERMS] ?? ''));
        update_post_meta($postId, self::META_EXTRA_TERMS, $extraTerms);
    }

    // ── Attachment-specific handlers (overlay + full edit page) ─────────────

    /**
     * Add a "Exclude from search index" field to the attachment details panel.
     *
     * This field appears in both the media-library overlay and the full
     * attachment edit page.  It uses the standard `attachments[{id}][key]`
     * naming convention so WordPress passes it through both save paths.
     *
     * @param array<string, mixed> $fields     Existing attachment fields.
     * @param \WP_Post             $attachment The attachment being edited.
     * @return array<string, mixed>
     */
    public function addAttachmentFields(array $fields, \WP_Post $attachment): array
    {
        if ($attachment->post_mime_type !== 'application/pdf') {
            return $fields;
        }

        $exclude = get_post_meta($attachment->ID, self::META_EXCLUDE, true) === '1';

        $fields['typesense_exclude'] = [
            'label' => '',
            'input' => 'html',
            'html'  => sprintf(
                '<label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;">'
                . '<input type="checkbox" name="attachments[%d][typesense_exclude]" value="1" %s style="width:16px;height:16px;margin:0;" /> %s'
                . '</label>',
                $attachment->ID,
                checked($exclude, true, false),
                esc_html__('Exclude from search index', 'typesense-search')
            ),
        ];

        return $fields;
    }

    /**
     * Persist the exclude flag when an attachment is saved.
     *
     * Fires for both the media-library overlay (AJAX) and the full
     * attachment edit page.  In both cases the value is submitted as
     * `attachments[{id}][typesense_exclude]`.
     *
     * @param int $attachmentId The attachment post ID.
     */
    public function saveAttachmentFields(int $attachmentId): void
    {
        if (!current_user_can('edit_post', $attachmentId)) {
            return;
        }

        $data    = $_POST['attachments'][$attachmentId] ?? [];
        $exclude = isset($data['typesense_exclude']) && $data['typesense_exclude'] === '1' ? '1' : '0';
        update_post_meta($attachmentId, self::META_EXCLUDE, $exclude);
    }
}
