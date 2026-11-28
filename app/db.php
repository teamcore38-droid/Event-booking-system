<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Colombo');
function ticketdesk_var_dir(): string {
    $dir = getenv('TICKETDESK_VAR_DIR');
    if ($dir === false || $dir === '') {
        $dir = $_SERVER['TICKETDESK_VAR_DIR'] ?? (__DIR__.'/../var');
    }
    return rtrim(str_replace('\\', '/', $dir), '/');
}
$config = json_decode(file_get_contents(ticketdesk_var_dir().'/database.json'), true, flags: JSON_THROW_ON_ERROR);
$db = new PDO('mysql:host=127.0.0.1;port=3318;dbname=ticketdesk;charset=utf8mb4','ticketdesk',$config['password'], [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false
]);
function query(string $sql, array $params=[]): PDOStatement {
    global $db;
    $stmt=$db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function one(string $sql,array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
function money($n): string { return 'LKR '.number_format((float)$n,2); }
function h($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }