<?php
declare(strict_types=1);
require __DIR__.'/../app/db.php';
$row = one('SELECT e.art,c.jpeg FROM events e LEFT JOIN event_covers c ON c.event_id=e.id WHERE e.id=?', [(int)($_GET['id']??0)]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');
if (!$row) { http_response_code(404); exit; }
if ($row['jpeg'] === null) {
    header('Location: assets/'.$row['art'].'.jpg');
    exit;
}
$etag = '"'.hash('sha256',$row['jpeg']).'"';
header('ETag: '.$etag);
if (($_SERVER['HTTP_IF_NONE_MATCH']??'') === $etag) { http_response_code(304); exit; }
header('Content-Type: image/jpeg');
echo $row['jpeg'];