<?php

/**
 * Minimal Modularity stand-in for integration tests (issue #7 scenario).
 * Loaded only by tests/integration/bootstrap.php — not for production.
 */

declare(strict_types=1);

namespace Modularity\Helper;

class Post
{
    public static function getPostTemplate(int $postId): string
    {
        return 'default';
    }
}

/** Stubs required by JobPostingEnricher when Municipio is not installed. */
class WpService
{
    public static function get(): self
    {
        return new self();
    }
}

class AcfService
{
    public static function get(): self
    {
        return new self();
    }
}

namespace Municipio\SchemaData\Utils\SchemaToPostTypesResolver;

class SchemaToPostTypeResolver
{
    public function __construct(mixed $acfService, mixed $wpService)
    {
    }

    /**
     * @return string[]
     */
    public function resolve(string $schema): array
    {
        return [];
    }
}

namespace Modularity;

class Editor
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getPostModules(int $postId): array
    {
        $modules = get_post_meta($postId, '_test_modularity_modules', true);

        return is_array($modules) ? $modules : [];
    }
}

class Display
{
    /**
     * @param object $module
     * @param array<string, mixed> $args
     * @param array<string, mixed> $data
     */
    public function outputModule(object $module, array $args = [], array $data = [], bool $echo = true): string
    {
        $post = get_post((int) ($module->ID ?? 0));
        if (!$post) {
            return '';
        }

        return '<p>' . esc_html($post->post_content) . '</p>';
    }
}

class App
{
    /** @var Display */
    public static $display;
}

App::$display = new Display();

\add_action('init', static function (): void {
    \register_post_type('mod-text', [
        'public' => true,
        'label'  => 'Text module (test stub)',
    ]);
});
