<?php
require_once __DIR__ . '/../includes/functions.php';

$viewer = current_user();
$user_id = (int) query_value('id');
if (!$viewer || $user_id <= 0) {
    http_response_code(403);
    exit;
}

if (($viewer['role'] ?? '') !== 'Admin' && $user_id !== (int) $viewer['id']) {
    http_response_code(403);
    exit;
}

$user = db_select_one('SELECT profile_photo FROM users WHERE id = ? LIMIT 1', array($user_id));
$path = profile_photo_file_path($user['profile_photo'] ?? '');
$mime = profile_photo_mime_type($path);
if ($mime === null || !is_file($path)) {
    http_response_code(404);
    exit;
}

$file_size = filesize($path);
if ($file_size === false) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) $file_size);
header('Cache-Control: private, max-age=86400');
header('Content-Disposition: inline; filename="medikaflow-profile-photo"');
readfile($path);
exit;
