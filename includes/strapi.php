<?php
/**
 * AralPal – Strapi CMS Helper
 *
 * Fetches content from the Strapi REST API.
 * Set STRAPI_URL and STRAPI_TOKEN in includes/config.php
 */

// ── Config (add these to includes/config.php) ─────────────────────────────────
// define('STRAPI_URL',   'http://localhost:1337');
// define('STRAPI_TOKEN', 'your-api-token-here');

/**
 * Fetch data from a Strapi REST API endpoint.
 *
 * @param  string $endpoint  e.g. '/api/lessons?populate=*&sort=createdAt:asc'
 * @param  int    $ttl       Cache lifetime in seconds (0 = no cache)
 * @return array|null        Decoded JSON data array, or null on failure
 */
function strapi_fetch(string $endpoint, int $ttl = 60): ?array
{
    $baseUrl = defined('STRAPI_URL')   ? rtrim(STRAPI_URL, '/')   : 'http://localhost:1337';
    $token   = defined('STRAPI_TOKEN') ? STRAPI_TOKEN             : '';

    $cacheDir  = sys_get_temp_dir() . '/aralpal_strapi_cache';
    $cacheFile = $cacheDir . '/' . md5($endpoint) . '.json';

    // Return cached response if still fresh
    if ($ttl > 0 && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = file_get_contents($cacheFile);
        if ($cached !== false) {
            return json_decode($cached, true);
        }
    }

    $url = $baseUrl . $endpoint;

    $opts = [
        'http' => [
            'method'  => 'GET',
            'header'  => implode("\r\n", [
                'Accept: application/json',
                $token ? 'Authorization: Bearer ' . $token : '',
            ]),
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ];

    $context  = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("Strapi fetch failed: $url");
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log("Strapi invalid JSON from: $url");
        return null;
    }

    // Cache the raw response
    if ($ttl > 0) {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cacheFile, $response, LOCK_EX);
    }

    return $decoded;
}

/**
 * Get all lessons from Strapi.
 *
 * @return array  Array of lesson arrays, each with keys:
 *                icon, category, title, description, level, duration, link
 */
function strapi_get_lessons(): array
{
    $data = strapi_fetch('/api/lessons?populate=*&sort=createdAt:asc', 120);

    if (!isset($data['data']) || !is_array($data['data'])) {
        return [];
    }

    $lessons = [];
    foreach ($data['data'] as $item) {
        $attr = $item['attributes'] ?? $item; // Strapi v4 uses attributes; v5 is flat
        $lessons[] = [
            'icon'        => $attr['icon']        ?? '📖',
            'category'    => $attr['category']    ?? 'General',
            'title'       => $attr['title']        ?? '',
            'description' => $attr['description'] ?? '',
            'level'       => $attr['level']        ?? 'Beginner',
            'duration'    => $attr['duration']     ?? '',
            'link'        => $attr['link']         ?? '#',
        ];
    }

    return $lessons;
}

/**
 * Get published announcements from Strapi.
 *
 * @return array
 */
function strapi_get_announcements(): array
{
    $data = strapi_fetch('/api/announcements?filters[publishedAt][$notNull]=true&sort=publishedAt:desc', 120);

    if (!isset($data['data']) || !is_array($data['data'])) {
        return [];
    }

    $announcements = [];
    foreach ($data['data'] as $item) {
        $attr = $item['attributes'] ?? $item;
        $announcements[] = [
            'title'        => $attr['title']       ?? '',
            'body'         => $attr['body']         ?? '',
            'published_at' => $attr['publishedAt']  ?? null,
        ];
    }

    return $announcements;
}

/**
 * Get homepage single-type content from Strapi.
 *
 * @return array  Keys: hero_title, hero_description, about_title, about_body
 */
function strapi_get_homepage(): array
{
    $data = strapi_fetch('/api/homepage-content', 300);

    $attr = $data['data']['attributes'] ?? $data['data'] ?? [];

    return [
        'hero_title'       => $attr['hero_title']       ?? '',
        'hero_description' => $attr['hero_description'] ?? '',
        'about_title'      => $attr['about_title']      ?? '',
        'about_body'       => $attr['about_body']       ?? '',
    ];
}
