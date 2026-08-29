# Server-side files that live outside this repo's folder

- `public_html.htaccess` → copy to `~/public_html/.htaccess` and delete
  `~/public_html/index.php` (the old 301 redirect). After that
  `https://datapot.org/` and `https://datapot.net/` serve this site directly
  with clean URLs (`/`, `/tools.php?tool=drop`, `/post.php?id=…`) while the
  code stays in `~/public_html/app/datapot_www`. Existing top-level folders
  such as `drop/` are unaffected.
