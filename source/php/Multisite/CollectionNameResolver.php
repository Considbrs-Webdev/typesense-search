<?php

namespace TypesenseSearch\Multisite;

class CollectionNameResolver
{
    public function identity(): array
    {
        return [
            'home' => rtrim((string) get_home_url(get_current_blog_id()), '/'),
            'environment' => wp_get_environment_type(),
        ];
    }

    public function resolve(string $prefix = ''): string
    {
        $identity = $this->identity();
        return self::name($identity['home'], $identity['environment'], get_current_blog_id(), $prefix);
    }

    public static function name(string $home, string $environment, int $blogId, string $prefix = ''): string
    {
        $url = parse_url($home);
        if (!$url || empty($url['host']) || $blogId < 1) {
            throw new \InvalidArgumentException('A canonical site URL and site ID are required.');
        }
        $slug = strtolower($url['host'] . (isset($url['port']) ? '-' . $url['port'] : '') . ($url['path'] ?? ''));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
        $prefix = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($prefix)), '-');
        if ($prefix !== '') {
            $slug = $slug !== '' ? $prefix . '_' . $slug : $prefix;
        }
        $suffix = '__' . preg_replace('/[^a-z0-9_-]/', '', strtolower($environment)) . '_b' . $blogId;
        return rtrim(substr($slug ?: 'site', 0, 128 - strlen($suffix)), '-') . $suffix;
    }
}
