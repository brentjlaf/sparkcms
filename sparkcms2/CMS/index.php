<?php
// File: index.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/settings.php';
// Load pages from JSON
$pagesFile = __DIR__ . '/data/pages.json';
$pages = get_cached_json($pagesFile);

$settings = get_site_settings();

$menusFile = __DIR__ . '/data/menus.json';
$menus = get_cached_json($menusFile);

$blogFile = __DIR__ . '/data/blog_posts.json';
$blogPosts = get_cached_json($blogFile);
$mediaFile = __DIR__ . '/data/media.json';
$mediaItems = get_cached_json($mediaFile);

// Base paths used by theme templates
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

function sparkcms_parse_blog_limit($value) {
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $limit ?: 6;
}

function sparkcms_normalize_blog_category($value) {
    return strtolower(trim((string) $value));
}

function sparkcms_parse_blog_categories($value) {
    if (is_array($value)) {
        $list = $value;
    } else {
        $list = explode(',', (string) $value);
    }

    $normalized = [];
    foreach ($list as $item) {
        $category = sparkcms_normalize_blog_category($item);
        if ($category === '') {
            continue;
        }
        if (!in_array($category, $normalized, true)) {
            $normalized[] = $category;
        }
    }

    return $normalized;
}

function sparkcms_format_blog_date($value) {
    if (empty($value)) {
        return '';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }
    return date('M j, Y', $timestamp);
}

function sparkcms_resolve_blog_detail_url($scriptBase, $prefix, $slug) {
    if (empty($slug)) {
        return '#';
    }
    $detail = trim((string) $prefix);
    if ($detail === '') {
        $detail = '/blog';
    }
    if (preg_match('#^https?://#i', $detail)) {
        return rtrim($detail, '/') . '/' . rawurlencode($slug);
    }
    $detail = trim($detail, '/');
    $base = trim((string) $scriptBase);
    $path = $detail !== '' ? $detail . '/' . rawurlencode($slug) : rawurlencode($slug);
    if ($base === '' || $base === '/') {
        return '/' . $path;
    }
    return rtrim($base, '/') . '/' . $path;
}

function sparkcms_add_class(DOMElement $element, $class) {
    $classes = preg_split('/\s+/', trim($element->getAttribute('class')));
    $classes = array_filter($classes, 'strlen');
    if (!in_array($class, $classes, true)) {
        $classes[] = $class;
    }
    if ($classes) {
        $element->setAttribute('class', implode(' ', $classes));
    }
}

function sparkcms_remove_class(DOMElement $element, $class) {
    $classes = preg_split('/\s+/', trim($element->getAttribute('class')));
    $classes = array_filter($classes, function ($item) use ($class) {
        return $item !== '' && $item !== $class;
    });
    if ($classes) {
        $element->setAttribute('class', implode(' ', $classes));
    } else {
        $element->removeAttribute('class');
    }
}

function sparkcms_create_blog_article(DOMDocument $dom, array $post, $detailUrl, $metaEnabled, $excerptEnabled) {
    $article = $dom->createElement('article');
    $article->setAttribute('class', 'blog-item');

    $titleEl = $dom->createElement('h3');
    $titleEl->setAttribute('class', 'blog-title');
    $linkEl = $dom->createElement('a');
    $linkEl->setAttribute('href', $detailUrl);
    $titleText = $post['title'] ?? 'Untitled Post';
    $linkEl->appendChild($dom->createTextNode($titleText));
    $titleEl->appendChild($linkEl);
    $article->appendChild($titleEl);

    if ($metaEnabled) {
        $parts = [];
        if (!empty($post['author'])) {
            $parts[] = $post['author'];
        }
        $dateValue = sparkcms_format_blog_date($post['publishDate'] ?? $post['createdAt'] ?? '');
        if ($dateValue !== '') {
            $parts[] = $dateValue;
        }
        if ($parts) {
            $metaEl = $dom->createElement('div');
            $metaEl->setAttribute('class', 'blog-meta');
            foreach ($parts as $value) {
                $span = $dom->createElement('span');
                $span->appendChild($dom->createTextNode($value));
                $metaEl->appendChild($span);
            }
            $article->appendChild($metaEl);
        }
    }

    if ($excerptEnabled && !empty($post['excerpt'])) {
        $excerptEl = $dom->createElement('p');
        $excerptEl->setAttribute('class', 'blog-excerpt');
        $excerptEl->appendChild($dom->createTextNode(strip_tags($post['excerpt'])));
        $article->appendChild($excerptEl);
    }

    $readMoreEl = $dom->createElement('a');
    $readMoreEl->setAttribute('class', 'blog-read-more');
    $readMoreEl->setAttribute('href', $detailUrl);
    $readMoreEl->appendChild($dom->createTextNode('Read more '));
    $arrow = $dom->createElement('span');
    $arrow->setAttribute('aria-hidden', 'true');
    $arrow->appendChild($dom->createTextNode('→'));
    $readMoreEl->appendChild($arrow);
    $article->appendChild($readMoreEl);

    return $article;
}

function sparkcms_hydrate_blog_lists($html, $scriptBase) {
    global $blogPosts;
    if (strpos($html, 'data-blog-list') === false || !class_exists('DOMDocument')) {
        return $html;
    }
    $published = array_values(array_filter($blogPosts, function ($post) {
        return is_array($post) && strtolower($post['status'] ?? '') === 'published';
    }));

    $libxmlState = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($libxmlState);
    if (!$loaded) {
        return $html;
    }

    $xpath = new DOMXPath($dom);
    $containers = $xpath->query('//*[@data-blog-list]');
    if (!$containers->length) {
        return $html;
    }

    foreach ($containers as $container) {
        if (!$container instanceof DOMElement) {
            continue;
        }

        $limit = sparkcms_parse_blog_limit($container->getAttribute('data-limit'));
        $categories = sparkcms_parse_blog_categories($container->getAttribute('data-category'));
        $showExcerpt = strtolower((string) $container->getAttribute('data-show-excerpt'));
        $showMeta = strtolower((string) $container->getAttribute('data-show-meta'));
        $emptyMessage = trim($container->getAttribute('data-empty')) ?: 'No posts available.';
        $detailBase = $container->getAttribute('data-base');

        $itemsHostNodeList = $xpath->query('.//*[@data-blog-items]', $container);
        $itemsHost = $itemsHostNodeList->length ? $itemsHostNodeList->item(0) : $container;
        if (!$itemsHost instanceof DOMElement) {
            continue;
        }

        while ($itemsHost->firstChild) {
            $itemsHost->removeChild($itemsHost->firstChild);
        }

        $filtered = array_filter($published, function ($post) use ($categories) {
            if (!$categories) {
                return true;
            }
            $postCategory = sparkcms_normalize_blog_category($post['category'] ?? '');
            if ($postCategory === '') {
                return false;
            }
            return in_array($postCategory, $categories, true);
        });

        usort($filtered, function ($a, $b) {
            $aDate = $a['publishDate'] ?? $a['createdAt'] ?? '';
            $bDate = $b['publishDate'] ?? $b['createdAt'] ?? '';
            $aTime = strtotime($aDate) ?: 0;
            $bTime = strtotime($bDate) ?: 0;
            return $bTime <=> $aTime;
        });

        if ($limit && count($filtered) > $limit) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        $emptyNodeList = $xpath->query('.//*[@data-blog-empty]', $container);
        $emptyNode = $emptyNodeList->length ? $emptyNodeList->item(0) : null;

        $excerptEnabled = !in_array($showExcerpt, ['no', 'false', '0'], true);
        $metaEnabled = !in_array($showMeta, ['no', 'false', '0'], true);

        if (!$filtered) {
            if ($emptyNode instanceof DOMElement) {
                while ($emptyNode->firstChild) {
                    $emptyNode->removeChild($emptyNode->firstChild);
                }
                $emptyNode->appendChild($dom->createTextNode($emptyMessage));
                sparkcms_remove_class($emptyNode, 'd-none');
            } else {
                $notice = $dom->createElement('div');
                $notice->setAttribute('class', 'blog-item blog-item--placeholder');
                $notice->appendChild($dom->createTextNode($emptyMessage));
                $itemsHost->appendChild($notice);
            }
            $container->setAttribute('data-blog-rendered', 'server');
            continue;
        }

        if ($emptyNode instanceof DOMElement) {
            sparkcms_add_class($emptyNode, 'd-none');
        }

        foreach ($filtered as $post) {
            $detailUrl = sparkcms_resolve_blog_detail_url($scriptBase, $detailBase, $post['slug'] ?? '');
            $article = sparkcms_create_blog_article($dom, $post, $detailUrl, $metaEnabled, $excerptEnabled);
            $itemsHost->appendChild($article);
        }

        $container->setAttribute('data-blog-rendered', 'server');
    }

    return $dom->saveHTML();
}

function render_theme_page($templateFile, $page, $scriptBase) {
    global $settings, $menus, $logged_in, $blogPosts;
    $themeBase = $scriptBase . '/theme';
    ob_start();
    include $templateFile;
    $html = ob_get_clean();
    $html = preg_replace('/<div class="drop-area"><\/div>/', $page['content'], $html);
    if (!$logged_in) {
        $html = preg_replace('#<templateSetting[^>]*>.*?</templateSetting>#si', '', $html);
        $html = preg_replace('#<div class="block-controls"[^>]*>.*?</div>#si', '', $html);
        $html = str_replace('draggable="true"', '', $html);
        $html = preg_replace('#\sdata-ts="[^"]*"#i', '', $html);
        $html = preg_replace('#\sdata-(?:blockid|template|original|active|custom_[A-Za-z0-9_-]+)="[^"]*"#i', '', $html);
    }
    if (!$logged_in) {
        $html = sparkcms_hydrate_blog_lists($html, $scriptBase);
    }
    echo $html;
}

// Determine page slug
$slug = isset($_GET['page']) ? sanitize_text($_GET['page']) : ($settings['homepage'] ?? 'home');

$logged_in = is_logged_in();
$preview_mode = isset($_GET['preview']) && $logged_in;

if ($slug === 'search') {
    $q = isset($_GET['q']) ? sanitize_text($_GET['q']) : '';
    $results = [];
    $lower = strtolower($q);
    foreach ($pages as $p) {
        if (!$logged_in && (empty($p['published']) || ($p['access'] ?? 'public') !== 'public')) {
            continue;
        }
        if ($q === '' || stripos($p['title'], $lower) !== false || stripos($p['slug'], $lower) !== false || stripos($p['content'], $lower) !== false) {
            $results[] = $p;
        }
    }
    foreach ($blogPosts as $b) {
        if ($q === '' || stripos($b['title'], $lower) !== false || stripos($b['slug'], $lower) !== false || stripos($b['excerpt'], $lower) !== false || stripos($b['content'], $lower) !== false || stripos($b['tags'], $lower) !== false) {
            $results[] = ['title' => $b['title'], 'slug' => $b['slug']];
        }
    }
    foreach ($mediaItems as $m) {
        $tags = isset($m['tags']) && is_array($m['tags']) ? implode(',', $m['tags']) : '';
        if ($q === '' || stripos($m['name'], $lower) !== false || stripos($m['file'], $lower) !== false || stripos($tags, $lower) !== false) {
            $results[] = ['title' => $m['name'], 'slug' => ltrim($m['file'], '/')];
        }
    }
    $content = '<div class="search-results"><h1>Search Results';
    if ($q !== '') { $content .= ' for &quot;' . htmlspecialchars($q) . '&quot;'; }
    $content .= '</h1>';
    if ($results) {
        $content .= '<ul>';
        foreach ($results as $r) {
            $content .= '<li><a href="' . htmlspecialchars($scriptBase . '/' . $r['slug']) . '">' . htmlspecialchars($r['title']) . '</a></li>';
        }
        $content .= '</ul>';
    } else {
        $content .= '<p>No results found</p>';
    }
    $content .= '</div>';
    $page = ['title' => 'Search', 'content' => $content];
    $templateFile = realpath(__DIR__ . '/../theme/templates/pages/search.php');
    if ($templateFile && file_exists($templateFile)) {
        render_theme_page($templateFile, $page, $scriptBase);
    } else {
        echo $content;
    }
    exit;
}

$pageIndex = null;
$page = null;
foreach ($pages as $i => $p) {
    if ($p['slug'] === $slug) {
        $pageIndex = $i;
        $page = $p;
        break;
    }
}

if (!$page) {
    http_response_code(404);
    $page = [
        'title' => 'Page Not Found',
        'content' =>
            '<h1>Page Not Found</h1>' .
            '<p>The page you are looking for might have been moved or deleted.</p>' .
            '<p><a href="' . htmlspecialchars($scriptBase) . '/">Return to homepage</a>' .
            ' or use the site search to find what you are looking for.</p>'
    ];
    $templateFile = realpath(__DIR__ . '/../theme/templates/pages/errors/404.php');
    if ($templateFile && file_exists($templateFile)) {
        render_theme_page($templateFile, $page, $scriptBase);
    } else {
        echo $page['content'];
    }
    exit;
}

if (empty($page['published']) && !$logged_in) {
    http_response_code(404);
    $page = [
        'title' => 'Page Not Found',
        'content' =>
            '<h1>Page Not Found</h1>' .
            '<p>The page you are looking for might have been moved or deleted.</p>' .
            '<p><a href="' . htmlspecialchars($scriptBase) . '/">Return to homepage</a>' .
            ' or use the site search to find what you are looking for.</p>'
    ];
    $templateFile = realpath(__DIR__ . '/../theme/templates/pages/errors/404.php');
    if ($templateFile && file_exists($templateFile)) {
        render_theme_page($templateFile, $page, $scriptBase);
    } else {
        echo $page['content'];
    }
    exit;
}

if (($page['access'] ?? 'public') !== 'public' && !$logged_in) {
    http_response_code(403);
    $page = [
        'title' => 'Restricted',
        'content' =>
            '<h1>Restricted</h1>' .
            '<p>You do not have permission to view this page.</p>' .
            '<p><a href="' . htmlspecialchars($scriptBase) . '/">Return to homepage</a>' .
            ' or <a href="' . htmlspecialchars($scriptBase) . '/CMS/login.php">log in</a> ' .
            'with an account that has access.</p>'
    ];
    $templateFile = realpath(__DIR__ . '/../theme/templates/pages/errors/403.php');
    if ($templateFile && file_exists($templateFile)) {
        render_theme_page($templateFile, $page, $scriptBase);
    } else {
        echo $page['content'];
    }
    exit;
}

// Increment views and persist
if ($pageIndex !== null) {
    $pages[$pageIndex]['views'] = ($pages[$pageIndex]['views'] ?? 0) + 1;
    $page = $pages[$pageIndex];
    file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT));
}

// If logged in show the page builder instead of the static page
if ($logged_in && !$preview_mode) {
    $_GET['id'] = $page['id'];
    require __DIR__ . '/../liveed/builder.php';
    return;
}

if ($preview_mode) {
    $logged_in = false;
}

$templateFile = null;
if (!empty($page['template'])) {
    $candidate = realpath(__DIR__ . '/../theme/templates/pages/' . $page['template']);
    if ($candidate && file_exists($candidate)) {
        $templateFile = $candidate;
    }
}
if ($templateFile && (!$logged_in || $preview_mode)) {
    render_theme_page($templateFile, $page, $scriptBase);
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<title><?php echo htmlspecialchars(($settings['site_name'] ?? 'SparkCMS') . ' - ' . $page['title']); ?></title>
<?php if (!empty($page['meta_title'])): ?>
    <meta name="title" content="<?php echo htmlspecialchars($page['meta_title']); ?>">
<?php endif; ?>
<?php if (!empty($page['meta_description'])): ?>
    <meta name="description" content="<?php echo htmlspecialchars($page['meta_description']); ?>">
<?php endif; ?>
<?php if (!empty($page['canonical_url'])): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($page['canonical_url']); ?>">
<?php endif; ?>
<?php if (!empty($page['og_title'])): ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($page['og_title']); ?>">
<?php endif; ?>
<?php if (!empty($page['og_description'])): ?>
    <meta property="og:description" content="<?php echo htmlspecialchars($page['og_description']); ?>">
<?php endif; ?>
<?php if (!empty($page['og_image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($page['og_image']); ?>">
<?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<?php if ($logged_in): ?>
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/root.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/skin.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/override.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/spark-cms.css">
<?php else: ?>
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/root.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/skin.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/override.css">
<?php endif; ?>
</head>
<body>
<?php if ($logged_in): ?>
<?php else: ?>
    <div class="header">
        <div class="logo">
        <?php if (!empty($settings['logo'])): ?>
            <img src="<?php echo htmlspecialchars($scriptBase . '/CMS/' . $settings['logo']); ?>" alt="Logo" style="height:40px;">
        <?php else: ?>
            <?php echo htmlspecialchars($settings['site_name'] ?? 'SparkCMS'); ?>
        <?php endif; ?>
        </div>
        <div>
            <a class="btn btn-primary" href="<?php echo $scriptBase; ?>/CMS/admin.php">
                <i class="fa-solid fa-gauge-high btn-icon" aria-hidden="true"></i>
                <span class="btn-label">Admin</span>
            </a>
        </div>
    </div>
    <div class="content">
        <?php echo $page['content']; ?>
    </div>
    <footer class="footer">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['site_name'] ?? 'SparkCMS'); ?>
    </footer>
<?php endif; ?>
<?php if ($logged_in): ?>
<script src="<?php echo $scriptBase; ?>/theme/js/combined.js"></script>
<?php else: ?>
<script src="<?php echo $scriptBase; ?>/theme/js/combined.js"></script>
<?php endif; ?>
</body>
</html>
