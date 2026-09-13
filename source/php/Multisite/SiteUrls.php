<?php

namespace TypesenseSearch\Multisite;

/** Target-site administration, including shared-core installations. */
class SiteUrls
{
    public static function admin(int $siteId, string $path = ''): string
    {
        $url = get_admin_url($siteId, $path);
        $site = get_site($siteId);
        if ($site && !is_subdomain_install()) {
            $mainId = (int) get_main_site_id((int) $site->network_id);
            // Some installations store the shared /wp core URL on every site.
            // Their subsite admin is routed via /subsite/wp-admin/, not /wp/wp-admin/.
            if ($siteId !== $mainId && get_site_url($siteId) === get_site_url($mainId)) {
                $url = trailingslashit(get_home_url($siteId)) . 'wp-admin/' . ltrim($path, '/');
            }
        }
        return (string) apply_filters('typesense_search_network_site_admin_url', $url, $siteId, $path);
    }
}
