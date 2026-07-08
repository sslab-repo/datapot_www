<?php
require __DIR__ . '/lib.php';
render_post((int)($_GET['id'] ?? 0));
