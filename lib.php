<?php
// datapot_www — shared config and page shell.
// Content itself is mirrored from WordPress by sync.php (a cron job), which
// writes plain HTML fragments to cache/*.html. Pages here only ever read
// those static files — no network calls or HTML parsing on a live request.

function cfg(): array {
    return require __DIR__ . '/config.php';
}

const CACHE_DIR = __DIR__ . '/cache';

const WP_PAGES = [
    'home'         => ['title' => 'Home',          'url' => 'http://sslab.us/press/'],
    'about'        => ['title' => 'About Dr. Cho',  'url' => 'http://sslab.us/press/2026/01/23/about-dr-cho/'],
    'publications' => ['title' => 'Publications',   'url' => 'http://sslab.us/press/publications/'],
    'career'       => ['title' => 'Career',         'url' => 'http://sslab.us/press/career/'],
    'links'        => ['title' => 'Links',          'url' => 'http://sslab.us/press/links/'],
    'contact'      => ['title' => 'Contact',        'url' => 'http://sslab.us/press/contact/'],
    'giving'       => ['title' => 'Giving',         'url' => 'http://sslab.us/press/giving/'],
];

// Top nav — deliberately a fixed, curated list rather than every mirrored
// page (career/links/contact/posts still exist and sync, just aren't linked here).
const NAV_ITEMS = [
    ['key' => 'home', 'label' => 'Home',      'href' => 'index.php'],
    ['key' => 'lab',  'label' => 'Lab Pages', 'href' => 'https://sslab.us', 'newtab' => true],
];

// Tools — shown in the hover flyout; tools.php renders the selected one.
// Add new tools here; the flyout and tools.php pick them up automatically.
// Tools hosted on this same domain (drop lives at datapot.org/drop and
// datapot.net/drop) use a relative 'url' so the iframe stays same-origin
// whichever domain the visitor came in on. Tools on other hosts (like DMS
// on hopper) use their absolute URL; embedding those additionally requires
// that host to allow framing from datapot.org (CSP frame-ancestors).
const TOOLS_ITEMS = [
    ['key' => 'drop', 'label' => 'Drop', 'url' => '/drop/', 'description' => 'Upload a file and get a short download link to share it.', 'embed' => true],
    ['key' => 'dms',  'label' => 'DMS',  'url' => 'https://hopper.cs.lewisu.edu/tools/dms',
     'description' => 'Dataset management platform — upload, search, browse, and download lab datasets with AI-generated metadata. Under development; coming soon.', 'embed' => false],
];

const WP_CATEGORIES = [
    'http://sslab.us/press/archives/category/academia-education/',
    'http://sslab.us/press/archives/category/system-operations/',
    'http://sslab.us/press/archives/category/network-cybersecurity/',
    'http://sslab.us/press/archives/category/software-development/',
];

// Reads cache/posts_index.json (written by sync.php), newest first.
// Returns [] if sync.php hasn't run yet.
function post_index(): array {
    $file = CACHE_DIR . '/posts_index.json';
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function render_header(string $active): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DataPot by Security Science Lab</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  /* Palette taken from lewisu.edu/css/styles.css — matches SSLab drop */
  :root { --red:#c22033; --darkRed:#940731; --black:#000; --white:#fff;
          --gray:#dcdcd7; --bg:#f1f1ef; --text:#1a1a1a; --muted:#7e7f74;
          --border:#dcdcd7; }
  * { box-sizing: border-box; }
  body { margin:0; font-family:'Montserrat', system-ui, sans-serif;
         background:var(--bg); color:var(--text);
         min-height:100vh; display:flex; flex-direction:column; }

  .topstrip { background:var(--black); color:var(--white);
              font-size:.72rem; letter-spacing:.08em; text-transform:uppercase;
              padding:.45rem 1.2rem; }
  .topstrip a { color:var(--white); text-decoration:none; }
  .masthead { background:var(--white); border-bottom:4px solid var(--red);
              padding:1rem 1.2rem; display:flex; align-items:baseline; gap:.75rem; flex-wrap:wrap; }
  .wordmark { color:var(--red); font-weight:800; letter-spacing:.06em;
              font-size:1.15rem; text-transform:uppercase; }
  .appname  { color:var(--black); font-weight:300; font-size:1.15rem; }

  nav.sitenav { background:var(--white); border-bottom:1px solid var(--border);
                padding:0 1.2rem; display:flex; gap:1.4rem; flex-wrap:wrap; }
  nav.sitenav a { color:var(--text); text-decoration:none; font-size:.85rem;
                  font-weight:600; padding:.75rem 0; border-bottom:3px solid transparent; }
  nav.sitenav a:hover { color:var(--red); }
  nav.sitenav a.active { color:var(--red); border-bottom-color:var(--red); }

  .nav-tools { position:relative; }
  .nav-tools-label { display:inline-block; color:var(--text); font-size:.85rem;
                      font-weight:600; padding:.75rem 0; border-bottom:3px solid transparent;
                      cursor:default; }
  .nav-tools:hover .nav-tools-label { color:var(--red); }
  .nav-tools-label.active { color:var(--red); border-bottom-color:var(--red); }
  .nav-tools-menu { display:none; position:absolute; top:0; left:100%; margin-left:.6rem;
                     background:var(--white); border:1px solid var(--border);
                     box-shadow:0 2px 10px rgba(0,0,0,.08); min-width:110px; z-index:10; }
  .nav-tools:hover .nav-tools-menu { display:block; }
  /* invisible bridge so the pointer can cross the gap without the menu closing */
  .nav-tools-menu::before { content:""; position:absolute; top:0; bottom:0; left:-.6rem; width:.6rem; }
  /* nav.sitenav prefix needed to out-rank the general "nav.sitenav a" rule,
     which otherwise zeroes the horizontal padding */
  nav.sitenav .nav-tools-menu a { display:block; padding:.4rem 1.6rem .4rem 1.2rem; color:var(--text);
                       text-decoration:none; font-size:.85rem; font-weight:600; white-space:nowrap;
                       border-bottom:0; }
  nav.sitenav .nav-tools-menu a:hover { background:var(--bg); color:var(--red); }

  main { flex:1; padding:2rem 1.2rem; }
  .col { width:100%; max-width:800px; margin:0 auto; }
  /* tools page: card hugs the embedded tool (540px iframe + card padding) */
  body.page-tools .col { max-width:calc(540px + 4rem + 2px); }
  .card { background:var(--white); border:1px solid var(--border);
          padding:1.8rem 2rem; box-shadow:0 2px 10px rgba(0,0,0,.05); }

  h1.page-title { margin:0 0 1.2rem; font-size:1.5rem; font-weight:700; color:var(--black); }
  .content h1, .content h2, .content h3, .content h4 { color:var(--black); font-weight:700; margin:1.4rem 0 .6rem; }
  .content h1 { font-size:1.4rem; }
  .content h2 { font-size:1.2rem; }
  .content h3, .content h4 { font-size:1.05rem; }
  .content p { line-height:1.65; margin:0 0 1rem; }
  .content a { color:var(--red); }
  .content a:hover { color:var(--darkRed); }
  .content img { max-width:100%; height:auto; }
  .content ul, .content ol { line-height:1.65; margin:0 0 1rem; padding-left:1.4rem; }
  .content figure { margin:1rem 0; }

  .unavailable { color:var(--muted); font-style:italic; }

  /* home: shortened preview with fade-out + More button */
  .content-preview { max-height:36rem; overflow:hidden; position:relative; }
  .content-preview::after { content:""; position:absolute; left:0; right:0; bottom:0;
                             height:5rem; background:linear-gradient(to bottom, transparent, var(--white)); }
  .more-btn { display:inline-block; margin-top:1rem; padding:.6rem 2.2rem;
              background:var(--red); color:var(--white); text-decoration:none;
              font-size:.85rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; }
  .more-btn:hover { background:var(--darkRed); }

  .post-list { list-style:none; margin:0; padding:0; }
  .post-list li { padding:1.1rem 0; border-bottom:1px solid var(--border); }
  .post-list li:last-child { border-bottom:0; }
  .post-list h2 { margin:0 0 .25rem; font-size:1.05rem; font-weight:700; }
  .post-list h2 a { color:var(--black); text-decoration:none; }
  .post-list h2 a:hover { color:var(--red); }
  .post-date { color:var(--muted); font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; }
  .post-excerpt { margin:.5rem 0 0; color:var(--text); line-height:1.55; font-size:.92rem; }
  .post-meta { margin:0 0 1.2rem; color:var(--muted); font-size:.82rem; text-transform:uppercase; letter-spacing:.04em; }
  .back-link { display:inline-block; margin-bottom:1.2rem; color:var(--red); text-decoration:none; font-size:.85rem; font-weight:600; }
  .back-link:hover { color:var(--darkRed); }

  .tool-address { margin:0 0 .5rem; font-family:ui-monospace, monospace; font-size:.88rem; }
  .tool-address a { color:var(--red); }
  .tool-description { margin:0; line-height:1.55; color:var(--text); }
  /* width tracks drop's own layout: 500px content column + side padding */
  .tool-embed { display:block; width:100%; max-width:540px; margin:0 auto;
                height:420px; border:0; background:transparent; }

  footer { background:var(--black); color:var(--white); padding:1.4rem 1.2rem;
           font-size:.78rem; line-height:1.6; margin-top:auto; }
  footer a { color:var(--white); }
  footer .fmuted { color:#bab9af; }
  .credit { display:flex; align-items:center; gap:.45rem; margin-top:.9rem;
            color:#bab9af; font-size:.74rem; }
  .credit svg { flex-shrink:0; }
</style>
</head>
<body class="page-<?= htmlspecialchars($active) ?>">

<div class="topstrip"><a href="https://lewisu.edu" target="_blank" rel="noopener">Lewis University</a> &nbsp;·&nbsp; <a href="https://sslab.us" target="_blank" rel="noopener">Security Science Lab</a></div>
<div class="masthead">
  <span class="wordmark">DataPot</span>
  <span class="appname">by Security Science Lab</span>
</div>
<nav class="sitenav">
<?php foreach (NAV_ITEMS as $item): ?>
  <a href="<?= htmlspecialchars($item['href']) ?>"<?= $item['key'] === $active ? ' class="active"' : '' ?><?= !empty($item['newtab']) ? ' target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($item['label']) ?></a>
<?php endforeach; ?>
  <div class="nav-tools">
    <span class="nav-tools-label<?= $active === 'tools' ? ' active' : '' ?>">Tools</span>
    <div class="nav-tools-menu">
    <?php foreach (TOOLS_ITEMS as $tool): ?>
      <a href="tools.php?tool=<?= htmlspecialchars($tool['key']) ?>"><?= htmlspecialchars($tool['label']) ?></a>
    <?php endforeach; ?>
    </div>
  </div>
</nav>

<main><div class="col">
<?php
}

function render_footer(): void {
?>
</div></main>

<footer>
  <div><strong>Copyright - Dr. Jake Cho, SSLab@Lewis University</strong></div>
  <div class="fmuted"><a href="mailto:sslab@lewisu.edu">sslab@lewisu.edu</a> ·
    <a href="tools.php">Tools</a></div>
  <div class="credit">
    <!-- Claude mark -->
    <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
      <g stroke="#D97757" stroke-width="2.4" stroke-linecap="round">
        <line x1="12" y1="2.5" x2="12" y2="7" />
        <line x1="12" y1="2.5" x2="12" y2="7" transform="rotate(45 12 12)" />
        <line x1="12" y1="2.5" x2="12" y2="7" transform="rotate(90 12 12)" />
        <line x1="12" y1="2.5" x2="12" y2="7" transform="rotate(135 12 12)" />
        <line x1="12" y1="2.5" x2="12" y2="7" transform="rotate(180 12 12)" />
        <line x1="12" y1="2.5" x2="12" y2="7" transform="rotate(225 12 12)" />
        <line x1="12" y1="2.5" x2="12" y2="7" transform="rotate(270 12 12)" />
        <line x1="12" y1="2.5" x2="12" y2="7" transform="rotate(315 12 12)" />
      </g>
    </svg>
    <span>Designed by SSLab, Coded by Fable 5</span>
  </div>
</footer>

</body>
</html>
<?php
}

// Renders the static Home page — what DataPot is, the tools it hosts,
// and the educational-purpose / acceptable-use notice. No WP mirroring.
function render_home(): void {
    render_header('home');
    ?>
  <div class="card">
    <h1 class="page-title">Welcome to DataPot</h1>
    <div class="content">
      <p><strong>DataPot</strong> is the public tool portal of the
        <a href="https://sslab.us" target="_blank" rel="noopener">Security Science Lab (SSLab)</a>
        at <a href="https://lewisu.edu" target="_blank" rel="noopener">Lewis University</a>.
        It provides a unified point of access to web-based utilities developed by the
        laboratory in support of its instructional and research activities, spanning
        areas such as data management, file exchange, and systems security.</p>

      <h2>Available Tools</h2>
      <ul>
      <?php foreach (TOOLS_ITEMS as $tool): ?>
        <li><a href="tools.php?tool=<?= htmlspecialchars($tool['key']) ?>"><?= htmlspecialchars($tool['label']) ?></a>
          — <?= htmlspecialchars($tool['description']) ?></li>
      <?php endforeach; ?>
      </ul>

      <h2>Development and Operations</h2>
      <p>The systems and tools offered here are designed jointly by students and
        faculty of the laboratory. All development, quality assurance, and
        operations management, however, are fully controlled by
        <strong>LUCA</strong> — the <em>Lewis University Collaborative AI</em> —
        an AI agent created by Dr.&nbsp;Cho and working with Claude.</p>

      <h2>Purpose and Acceptable Use</h2>
      <p>This platform is developed and operated for educational purposes. Open access
        is provided in the spirit of academic exchange, and all visitors are welcome
        to make use of the tools offered here. Users are expected to act responsibly
        and to refrain from any activity prohibited by federal law.</p>

      <p>Comments and suggestions may be directed to
        <a href="mailto:sslab@lewisu.edu">sslab@lewisu.edu</a>.</p>
    </div>
  </div>
    <?php
    render_footer();
}

// Renders a full mirrored WP page: header, title, content card, footer.
// Content comes from the static cache/{key}.html file written by sync.php —
// this function does no fetching or parsing itself.
function render_wp_page(string $key): void {
    $cacheFile = CACHE_DIR . '/' . $key . '.html';
    $html = is_file($cacheFile) ? file_get_contents($cacheFile) : '';

    render_header($key);
    ?>
  <div class="card">
    <h1 class="page-title"><?= htmlspecialchars(WP_PAGES[$key]['title']) ?></h1>
    <?php
    // Shortened-preview pages: key => where the More button leads.
    $previews = [
        'home'         => 'https://sslab.us',
        'about'        => WP_PAGES['about']['url'],
        'publications' => WP_PAGES['publications']['url'],
    ];
    $isPreview = isset($previews[$key]);
    ?>
    <div class="content<?= $isPreview ? ' content-preview' : '' ?>">
    <?php if ($html !== ''): ?>
      <?= $html ?>
    <?php else: ?>
      <p class="unavailable">This content is temporarily unavailable. Please check back shortly.</p>
    <?php endif; ?>
    </div>
    <?php if ($isPreview): ?>
    <a class="more-btn" href="<?= htmlspecialchars($previews[$key]) ?>" target="_blank" rel="noopener">More</a>
    <?php endif; ?>
  </div>
    <?php
    render_footer();
}

// Looks up a tool by key.
function find_tool(string $key): ?array {
    foreach (TOOLS_ITEMS as $tool) {
        if ($tool['key'] === $key) {
            return $tool;
        }
    }
    return null;
}

// Renders the "Tools" page — the single selected tool only (defaults to the
// first entry in TOOLS_ITEMS if none/unknown is requested). A tool with
// 'embed' => true shows its live page directly in the body via an iframe
// that auto-resizes to fit its content (the embedded app posts its height
// back — see drop/index.php for the sending side). Add entries to
// TOOLS_ITEMS to extend; no other changes needed here.
function render_tools(string $key): void {
    $tool = find_tool($key) ?? TOOLS_ITEMS[0];

    render_header('tools');
    ?>
  <div class="card">
    <?php if ($tool['embed']): ?>
    <iframe class="tool-embed" src="<?= htmlspecialchars($tool['url']) ?>"
            title="<?= htmlspecialchars($tool['label']) ?>" loading="lazy"></iframe>
    <?php else: ?>
    <h1 class="page-title"><?= htmlspecialchars($tool['label']) ?></h1>
    <p class="tool-address"><a href="<?= htmlspecialchars($tool['url']) ?>"><?= htmlspecialchars($tool['url']) ?></a></p>
    <?php if ($tool['description'] !== ''): ?>
    <p class="tool-description"><?= htmlspecialchars($tool['description']) ?></p>
    <?php endif; ?>
    <p class="unavailable">This tool isn't set up for in-page embedding yet — use the address above.</p>
    <?php endif; ?>
  </div>
  <script>
    // Auto-resize the embedded tool iframe to match its real content height.
    window.addEventListener('message', (e) => {
      if (!e.data || typeof e.data.height !== 'number') return;
      const f = document.querySelector('iframe.tool-embed');
      if (f && f.contentWindow === e.source) f.style.height = e.data.height + 'px';
    });
  </script>
    <?php
    render_footer();
}

// Renders the "Posts" listing page from cache/posts_index.json.
function render_post_list(): void {
    $posts = post_index();
    render_header('posts');
    ?>
  <div class="card">
    <h1 class="page-title">Posts</h1>
    <?php if ($posts): ?>
    <ul class="post-list">
      <?php foreach ($posts as $post): ?>
      <li>
        <h2><a href="post.php?id=<?= (int)$post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
        <div class="post-date"><?= htmlspecialchars($post['date']) ?></div>
        <p class="post-excerpt"><?= htmlspecialchars($post['excerpt']) ?></p>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="unavailable">No posts available yet. Please check back shortly.</p>
    <?php endif; ?>
  </div>
    <?php
    render_footer();
}

// Renders a single mirrored blog post by id, from cache/posts/{id}.html.
function render_post(int $id): void {
    $posts = post_index();
    $match = null;
    foreach ($posts as $post) {
        if ((int)$post['id'] === $id) {
            $match = $post;
            break;
        }
    }

    if ($match === null) {
        http_response_code(404);
    }
    render_header('posts');
    ?>
  <div class="card">
    <a class="back-link" href="posts.php">&larr; All posts</a>
    <?php if ($match === null): ?>
    <h1 class="page-title">Post not found</h1>
    <p class="unavailable">This post is unavailable. It may still be syncing, or the link may be out of date.</p>
    <?php else:
      $cacheFile = CACHE_DIR . "/posts/$id.html";
      $html = is_file($cacheFile) ? file_get_contents($cacheFile) : '';
    ?>
    <h1 class="page-title"><?= htmlspecialchars($match['title']) ?></h1>
    <div class="post-meta"><?= htmlspecialchars($match['date']) ?></div>
    <div class="content">
    <?php if ($html !== ''): ?>
      <?= $html ?>
    <?php else: ?>
      <p class="unavailable">This content is temporarily unavailable. Please check back shortly.</p>
    <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
    <?php
    render_footer();
}
