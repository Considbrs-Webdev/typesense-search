<?php

namespace TypesenseSearch\Admin;

/**
 * Class MetaBox
 *
 * Registers a "Typesense Search" meta box on every post type that has
 * indexing enabled in the plugin settings.
 *
 * Exposed meta keys:
 *   MetaBox::META_EXCLUDE      (_typesense_exclude)      — '1' = excluded from index
 *   MetaBox::META_EXTRA_TERMS  (_typesense_extra_terms)  — additional search terms string
 *
 * @package TypesenseSearch\Admin
 */
class MetaBox
{
    public const META_EXCLUDE     = '_typesense_exclude';
    public const META_EXTRA_TERMS = '_typesense_extra_terms';

    private const NONCE_ACTION = 'typesense_metabox_save';
    private const NONCE_FIELD  = '_typesense_metabox_nonce';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register']);
        add_action('save_post',      [$this, 'save'], 10, 2);
    }

    /**
     * Register the meta box for every currently-enabled post type.
     */
    public function register(): void
    {
        $enabledPostTypes = (array) get_option(Settings::OPTION_POST_TYPES, []);

        foreach ($enabledPostTypes as $postType) {
            add_meta_box(
                'typesense-search',
                __('Typesense Search', 'typesense-search'),
                [$this, 'render'],
                $postType,
                'side',
                'default'
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

        $exclude    = get_post_meta($post->ID, self::META_EXCLUDE, true) === '1';
        $extraTerms = (string) get_post_meta($post->ID, self::META_EXTRA_TERMS, true);
        ?>

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

        // ── Extra search terms ────────────────────────────────────────────────
        $extraTerms = sanitize_textarea_field(wp_unslash($_POST[self::META_EXTRA_TERMS] ?? ''));
        update_post_meta($postId, self::META_EXTRA_TERMS, $extraTerms);
    }
}
