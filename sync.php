<?php
// datapot_www — cron target: pulls WordPress content and writes static HTML
// fragments under cache/. Page requests (index.php, posts.php, post.php,
// etc.) only ever read those static files — no network calls or HTML
// parsing happens on a live visitor request.
//
// Preferred crontab (every 15 minutes):
//   */15 * * * * php /path/to/datapot_www/sync.php
//
// If your host's cron can only fetch URLs, use the token from config.php:
//   */15 * * * * wget -qO- "https://datapot.org/sync.php?token=YOUR_CRON_TOKEN"
require __DIR__ . '/lib.php';

$isCli = php_sapi_name() === 'cli';
if (!$isCli && !hash_equals(cfg()['cron_token'], (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden.');
}

@is_dir(CACHE_DIR) || @mkdir(CACHE_DIR, 0775, true);
@is_dir(CACHE_DIR . '/posts') || @mkdir(CACHE_DIR . '/posts', 0775, true);

$ok = 0;
$failed = 0;

// --- static nav pages -------------------------------------------------
foreach (WP_PAGES as $key => $page) {
    $xpath = fetch_dom($page['url']);
    $html = $xpath ? extract_entry_content($xpath) : null;
    if ($html !== null) {
        file_put_contents(CACHE_DIR . '/' . $key . '.html', $html);
        $ok++;
    } else {
        $failed++;
        // Leave the previous cache file in place — stale beats blank.
    }
}

// --- blog posts: discover via category archives, dedupe, fetch each --
$posts = []; // id => ['title'=>, 'datetime'=>, 'excerpt'=>, 'url'=>]
foreach (WP_CATEGORIES as $categoryUrl) {
    $xpath = fetch_dom($categoryUrl);
    if (!$xpath) {
        $failed++;
        continue;
    }
    foreach (extract_post_list($xpath) as $post) {
        $posts[$post['id']] = $post; // last-seen metadata wins; identical across categories
    }
}

uasort($posts, fn($a, $b) => strcmp($b['datetime'], $a['datetime']));

$index = [];
foreach ($posts as $id => $post) {
    $xpath = fetch_dom($post['url']);
    $html = $xpath ? extract_entry_content($xpath) : null;
    if ($html !== null) {
        file_put_contents(CACHE_DIR . "/posts/$id.html", $html);
        $index[] = [
            'id' => $id,
            'title' => $post['title'],
            'date' => date('F j, Y', strtotime($post['datetime'])),
            'excerpt' => $post['excerpt'],
        ];
        $ok++;
    } else {
        $failed++;
        // Keep the post in the index only if we already have its cached content.
        if (is_file(CACHE_DIR . "/posts/$id.html")) {
            $index[] = [
                'id' => $id,
                'title' => $post['title'],
                'date' => date('F j, Y', strtotime($post['datetime'])),
                'excerpt' => $post['excerpt'],
            ];
        }
    }
}
file_put_contents(CACHE_DIR . '/posts_index.json', json_encode($index));

echo "synced: $ok, failed: $failed\n";

// --- helpers ------------------------------------------------------------

// Fetches a URL and returns a DOMXPath over the parsed HTML, or null on failure.
function fetch_dom(string $url): ?DOMXPath {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SSLab-datapot-sync/1.0)',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false || $code !== 200) {
        return null;
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $body);
    libxml_clear_errors();
    return new DOMXPath($doc);
}

// Returns the inner HTML of the page's <div class="entry-content">, or null.
function extract_entry_content(DOMXPath $xpath): ?string {
    $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " entry-content ")]');
    if ($nodes->length === 0) {
        return null;
    }
    $doc = $nodes->item(0)->ownerDocument;
    $html = '';
    foreach ($nodes->item(0)->childNodes as $child) {
        $html .= $doc->saveHTML($child);
    }
    return $html;
}

// Parses a category archive's <ul class="post-list"> into
// [id => ['id'=>, 'title'=>, 'datetime'=>, 'excerpt'=>, 'url'=>]].
function extract_post_list(DOMXPath $xpath): array {
    $items = $xpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " post-list ")]'
        . '/li[contains(concat(" ", normalize-space(@class), " "), " post-item ")]');
    $posts = [];
    foreach ($items as $li) {
        $link = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " post-link ")]', $li)->item(0);
        $title = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " post-title ")]', $li)->item(0);
        $time = $xpath->query('.//time[contains(concat(" ", normalize-space(@class), " "), " post-time ")]', $li)->item(0);
        $desc = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " post-description ")]', $li)->item(0);
        if (!$link || !$title || !$time) {
            continue;
        }
        $href = $link->getAttribute('href');
        if (!preg_match('~/archives/(\d+)~', $href, $m)) {
            continue;
        }
        $id = (int)$m[1];
        $posts[$id] = [
            'id' => $id,
            'title' => trim($title->textContent),
            'datetime' => $time->getAttribute('datetime'),
            'excerpt' => $desc ? trim($desc->textContent) : '',
            'url' => $href,
        ];
    }
    return $posts;
}
