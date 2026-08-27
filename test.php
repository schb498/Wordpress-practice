<?php
/**
 * WordPress REST API test:authentication and fetch 1st post
 */

// --- Load .env ----------------------------------------------------------
$env = [];
foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value);
}

$base     = rtrim($env['WP_BASE_URL'] ?? '', '/');
$user     = $env['WP_USER'] ?? '';
$password = $env['WP_APP_PASSWORD'] ?? '';

// Fetch the first post, ordered by ID ascending 
$fields = 'id,type,title,status,modified,link';
$url    = "{$base}/wp-json/wp/v2/posts?orderby=id&order=asc&per_page=1&_fields={$fields}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
    CURLOPT_USERPWD        => "{$user}:{$password}",   // Basic Auth (RFC 7617)
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_TIMEOUT        => 30,
]);

// Windows PHP ships without a CA bundle; use the local one if php.ini has none.
$caBundle = __DIR__ . '/cacert.pem';
if (ini_get('curl.cainfo') === '' && ini_get('openssl.cafile') === '' && is_readable($caBundle)) {
    curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
}

$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($body === false) {
    exit('cURL error: ' . curl_error($ch) . "\n");
}
if ($status !== 200) {
    exit("HTTP {$status}\n{$body}\n");
}

// Raw JSON response
echo "JSON response\n";
echo json_encode(json_decode($body), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

// Filtered output
$posts = json_decode($body, true);
if (empty($posts)) {
    exit("No posts found.\n");
}

echo "Filtered fields\n";
$p = $posts[0];
echo "ID:       {$p['id']}\n";
echo "Type:     {$p['type']}\n";
echo "Title:    {$p['title']['rendered']}\n";
echo "Status:   {$p['status']}\n";
echo "Modified: {$p['modified']}\n";
echo "Link:     {$p['link']}\n";
