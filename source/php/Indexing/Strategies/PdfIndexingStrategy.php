<?php

namespace TypesenseSearch\Indexing\Strategies;

use TypesenseSearch\Admin\MetaBox;
use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Helper\ExcerptHelper;
use TypesenseSearch\Helper\PdfToText;
use TypesenseSearch\Indexing\IndexableDocument;

/**
 * Class PdfIndexingStrategy
 *
 * Indexing strategy for PDF files from the WordPress media library.
 *
 * A PDF attachment is indexed as a Typesense document with:
 *   - title       : the attachment's post_title (editable in the media library)
 *   - content     : plain text extracted via pdftotext, whitespace-normalised
 *                   and trimmed to DEFAULT_MAX_CONTENT_LENGTH characters
 *   - excerpt     : trimmed version of the extracted text (via ExcerptHelper)
 *   - url         : direct URL to the PDF file
 *   - type        : 'attachment'
 *   - type_name   : 'PDF'
 *   - top_most_parent : title of the top-level ancestor of the page the PDF is
 *                       attached to, if any
 *
 * Indexing only runs when all of these conditions hold:
 *   1. The OPTION_INDEX_PDF setting is enabled.
 *   2. The pdftotext binary is available on the server.
 *   3. The attachment's MetaBox::META_EXCLUDE flag is not set.
 *
 * @package TypesenseSearch\Indexing\Strategies
 */
class PdfIndexingStrategy extends AbstractIndexingStrategy
{
    /**
     * Default maximum length in characters for the indexed content field.
     */
    public const DEFAULT_MAX_CONTENT_LENGTH = 50000;

    /**
     * Filter hook to override the maximum content length.
     * Receives: (int $maxLength, \WP_Post $attachment)
     *
     * The hook name retains the legacy "PdfAttachmentAdapter" segment so that
     * existing external hooks registered against this string continue to work.
     */
    public const FILTER_MAX_CONTENT_LENGTH = 'Municipio/TypesenseSearch/PdfAttachmentAdapter/max_content_length';

    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return 'pdf';
    }

    /**
     * {@inheritdoc}
     *
     * Returns true for PDF attachments only.
     */
    public function supports(\WP_Post $post): bool
    {
        return $post->post_type === 'attachment'
            && $post->post_mime_type === 'application/pdf';
    }

    /**
     * {@inheritdoc}
     *
     * Checks that PDF indexing is active, the post is a PDF attachment,
     * and the attachment has not been manually excluded.
     */
    public function shouldIndex(\WP_Post $post): bool
    {
        if (get_post_meta($post->ID, MetaBox::META_EXCLUDE, true) === '1') {
            return false;
        }

        return self::isActive()
            && $post->post_type === 'attachment'
            && $post->post_mime_type === 'application/pdf';
    }

    /**
     * {@inheritdoc}
     *
     * Extracts text from the PDF file and builds a Typesense document.
     * Returns false when the file cannot be read or extraction fails.
     */
    public function buildDocument(\WP_Post $post): IndexableDocument|false
    {
        $filePath = get_attached_file($post->ID);
        if (!$filePath || !is_readable($filePath)) {
            return false;
        }

        $content = PdfToText::extractText($filePath);
        if ($content === false) {
            $this->logger->error(sprintf(
                '[TypesenseSearch] pdftotext failed for attachment %d (%s)',
                $post->ID,
                $filePath
            ));
            return false;
        }

        $content = self::trimContent($content, $post);

        $dateTimestamp = (int) strtotime((string) $post->post_date_gmt);

        return new IndexableDocument([
            'id'                  => (string) $post->ID,
            'title'               => (string) $post->post_title,
            'content'             => $content,
            'excerpt'             => ExcerptHelper::build($content, $post),
            'url'                 => (string) wp_get_attachment_url($post->ID),
            'type'                => 'attachment',
            'type_name'           => __('PDF document', 'typesense-search'),
            'date'                => $dateTimestamp,
            'post_date_formatted' => $dateTimestamp > 0
                ? (string) date_i18n(get_option('date_format'), $dateTimestamp)
                : '',
            'thumbnail'           => '',
            'extra_terms'         => '',
            'top_most_parent'     => self::resolveTopMostParent($post),
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * Registers PDF-specific WordPress hooks for attachment lifecycle events.
     */
    public function registerHooks(): void
    {
        add_action('add_attachment', [$this, 'onAddAttachment']);
        add_action('edit_attachment', [$this, 'onEditAttachment']);
        add_action('delete_attachment', [$this, 'onDeleteAttachment']);
    }

    // ── Hook callbacks ──────────────────────────────────────────────────────

    /**
     * Triggered after a new attachment is added to the media library.
     *
     * @param int $attachmentId The new attachment's post ID.
     */
    public function onAddAttachment(int $attachmentId): void
    {
        if (!self::isActive()) {
            return;
        }

        $attachment = get_post($attachmentId);
        if (!$attachment || !$this->shouldIndex($attachment)) {
            return;
        }

        $this->index($attachment);
    }

    /**
     * Triggered after an attachment record is updated.
     *
     * @param int $attachmentId The attachment's post ID.
     */
    public function onEditAttachment(int $attachmentId): void
    {
        if (!self::isActive()) {
            return;
        }

        $attachment = get_post($attachmentId);
        if (!$attachment) {
            return;
        }

        if ($this->shouldIndex($attachment)) {
            $this->index($attachment);
        } else {
            $this->deindex($attachmentId);
        }
    }

    /**
     * Triggered just before an attachment is permanently deleted.
     *
     * @param int $attachmentId The attachment's post ID.
     */
    public function onDeleteAttachment(int $attachmentId): void
    {
        $this->deindex($attachmentId);
    }

    /**
     * Re-index all PDF attachments that are direct children of the given post.
     *
     * Called when a parent page is published/updated so that the
     * top_most_parent field stays in sync.
     *
     * @param int $postId Parent post ID.
     */
    public function reindexAttachedPdfs(int $postId): void
    {
        if (!self::isActive()) {
            return;
        }

        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_parent'    => $postId,
            'post_mime_type' => 'application/pdf',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);

        foreach ($attachments as $attachment) {
            $this->index($attachment);
        }
    }

    // ── Static helpers ──────────────────────────────────────────────────────

    /**
     * Return true when PDF indexing is both enabled in settings AND the
     * pdftotext binary is available on the server.
     */
    public static function isActive(): bool
    {
        return (bool) get_option(Settings::OPTION_INDEX_PDF, 0)
            && PdfToText::isAvailable();
    }

    // ── Internal helpers ────────────────────────────────────────────────────

    /**
     * Trim extracted PDF text to the configured maximum length.
     *
     * @param string   $content    Raw extracted text.
     * @param \WP_Post $attachment The attachment post.
     * @return string
     */
    private static function trimContent(string $content, \WP_Post $attachment): string
    {
        $content = (string) preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        $maxLength = (int) apply_filters(
            self::FILTER_MAX_CONTENT_LENGTH,
            self::DEFAULT_MAX_CONTENT_LENGTH,
            $attachment
        );

        if ($maxLength <= 0 || mb_strlen($content) <= $maxLength) {
            return $content;
        }

        $trimmed   = mb_substr($content, 0, $maxLength);
        $lastSpace = mb_strrpos($trimmed, ' ');
        if ($lastSpace !== false) {
            $trimmed = mb_substr($trimmed, 0, $lastSpace);
        }

        return rtrim($trimmed) . ' [...]';
    }

    /**
     * Resolve the title of the top-most ancestor page for an attachment.
     *
     * @param \WP_Post $attachment
     * @return string
     */
    private static function resolveTopMostParent(\WP_Post $attachment): string
    {
        $parentId = (int) $attachment->post_parent;
        if ($parentId === 0) {
            return '';
        }

        $parent = get_post($parentId);
        if (!$parent) {
            return '';
        }

        $ancestors = get_post_ancestors($parent);
        $topPost   = !empty($ancestors) ? get_post((int) end($ancestors)) : $parent;

        if (!$topPost || self::isExcludedAsSection($topPost)) {
            return '';
        }

        return (string) $topPost->post_title;
    }

    /**
     * Check whether a page should be hidden from the section facet.
     *
     * @param \WP_Post $post
     * @return bool
     */
    private static function isExcludedAsSection(\WP_Post $post): bool
    {
        return $post->post_type === 'page'
            && get_post_meta($post->ID, MetaBox::META_EXCLUDE_AS_SECTION, true) === '1';
    }
}
