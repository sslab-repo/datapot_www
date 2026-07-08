<?php
// datapot_www — configuration. Keep this file NOT web-readable (.htaccess denies it).
return [
    // Secret token so cron can call sync.php over HTTP (if your host's cron
    // uses wget/curl instead of the php CLI). Change it.
    'cron_token' => 'CHANGE_ME_cron_token',
];
