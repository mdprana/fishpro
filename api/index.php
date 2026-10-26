<?php
// Vercel entry. Lambda FS is read-only except /tmp, so the seeded demo DB is copied there per cold start.
// ponytail: data resets on every cold start/instance; point DB_* at a hosted MySQL for persistence.
$db = '/tmp/fishpro.sqlite';
if (!file_exists($db)) {
    copy(__DIR__.'/../database/demo.db', $db);
}
@mkdir('/tmp/views', 0777, true);

require __DIR__.'/../public/index.php';
