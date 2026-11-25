<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/actions.php';
require __DIR__.'/../app/images.php';
require __DIR__.'/../app/reports.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);session_start();
header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');
$_SESSION['csrf']??=bin2hex(random_bytes(24));
$p=$_GET['p']??'home';$id=(int)($_GET['id']??0);
$user=$_SESSION['user']??null;$role=$user['role']??'';
function formStart($act,$id=0){echo '<form method="post"'.($act==='event'?' enctype="multipart/form-data"':'').'><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="action" value="'.h($act).'"><input type="hidden" name="id" value="'.(int)$id.'">';}
function inp($name,$label,$value='',$type='text',$attrs=''){echo '<label>'.h($label).'<input required name="'.h($name).'" type="'.h($type).'" value="'.h($value).'" '.$attrs.'></label>';}
function stamp($dt){return date('D, j M Y · g:i A',strtotime($dt));}
function errorText(Throwable $e): string {
    global $db;if($db->inTransaction())$db->rollBack();
    if($e instanceof DomainException)return $e->getMessage();
    if($e instanceof PDOException && ($e->errorInfo[1]??0)===1062)return 'That entry already exists. Check the details and try again.';
    error_log((string)$e);return 'The request could not be completed. Please try again.';
}
$error='';
try{
    if($_SERVER['REQUEST_METHOD']==='POST')go(action($_POST['action']??''));
    if($p==='availability'){
        $categories=query('SELECT c.id,c.capacity,e.starts,t.seat,t.id ticket FROM categories c JOIN events e ON e.id=c.event_id LEFT JOIN tickets t ON t.category_id=c.id WHERE e.id=?',[$id]);
        $items=[];
        foreach($categories as $row){
            $cid=$row['id'];
            $items[$cid]??=['id'=>$cid,'remaining'=>$row['starts']>date('Y-m-d H:i:s')?(int)$row['capacity']:0,'occupied'=>[]];
            if($row['ticket']) $items[$cid]['remaining']=max(0,$items[$cid]['remaining']-1);
            if($row['seat']) $items[$cid]['occupied'][]=(int)$row['seat'];
        }
        header('Content-Type: application/json');echo json_encode(array_values($items));exit;
    }
    if($p==='export')exportReport($id,$_GET['format']??'pdf');
    if($p==='download'){
        downloadTicket(ticketFor($id));
    }
}catch(Throwable $e){$error=errorText($e);}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ticketdesk · Events & bookings</title><link rel="stylesheet" href="assets/bootstrap.min.css"><link rel="stylesheet" href="assets/icons/bootstrap-icons.min.css"><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/wallet.css"></head><body class="view-<?=h($p)?>"><a class="skip" href="#main">Skip to content</a>
<header><div class="frame bar"><a class="brand" href="./"><span class="brand-symbol" aria-hidden="true"><i class="bi bi-ticket-perforated-fill"></i></span>Ticketdesk</a><nav><a href="./" aria-current="<?= $p==='home'?'page':'false' ?>"><i class="bi bi-compass"></i> Explore events</a><?php if($role==='participant'):?><a aria-current="<?= $p==='mine'?'page':'false' ?>" href="?p=mine">My tickets</a><?php endif;if($role==='admin'):?><a aria-current="<?= $p==='admin'?'page':'false' ?>" href="?p=admin">Manage</a><a aria-current="<?= $p==='reports'?'page':'false' ?>" href="?p=reports">Reports</a><?php endif;if(in_array($role,['admin','staff'])):?><a aria-current="<?= $p==='check'?'page':'false' ?>" href="?p=check">Check-in</a><?php endif;?></nav><?php if($user){formStart('logout');echo '<button class="quiet">Sign out</button></form>';}else{?><a class="sign" href="?p=login"><i class="bi bi-person-circle"></i> Sign in</a><?php }?></div></header>
<main id="main" class="frame"><?php if($error)echo '<p role="alert" class="notice error">'.h($error).'</p>';if(isset($_SESSION['message'])){echo '<p role="status" class="notice">'.h($_SESSION['message']).'</p>';unset($_SESSION['message']);}
ob_start();try{
switch($p){
case 'home':require __DIR__.'/../app/explore.php';break;
case 'event':
$e=one('SELECT * FROM events WHERE id=?',[$id]);if(!$e)throw new DomainException('Event unavailable.');?>
<a class="back" href="./">← Explore events</a><section class="hero" style="--photo:url('/event-cover.php?id=<?=(int)$e['id']?>')"><div><span class="eyebrow"><?=h($e['genre'])?></span><h1><?=h($e['title'])?></h1><p><?=h(stamp($e['starts']).' / '.$e['venue'])?></p></div></section><div class="split"><article><h2>About this event</h2><p><?=nl2br(h($e['description']))?></p></article><article data-event-inventory="<?=$id?>"><h2>Choose a ticket</h2><?php
foreach(query('SELECT c.*,(SELECT COUNT(*) FROM tickets t WHERE t.category_id=c.id) sold FROM categories c WHERE event_id=? ORDER BY price',[$id]) as $c){
$left=$c['capacity']-$c['sold'];?><section class="panel" data-category="<?=$c['id']?>"><h3><?=h($c['name'])?></h3><div class="rate"><strong><?=h(money($c['price']))?></strong><span><span data-remaining><?=$left?></span> available</span></div><?php
if($left<=0||$e['starts']<=date('Y-m-d H:i:s')) echo '<strong>Booking closed</strong>';
elseif(!$user)echo '<a class="button" href="?p=login&event='.$id.'">Sign in to book</a>';
elseif($role==='participant'){
formStart('book',$id);?><input type="hidden" name="request_key" value="<?=bin2hex(random_bytes(16))?>"><input type="hidden" name="category" value="<?=$c['id']?>"><?php
if($c['numbered']){ $taken=array_flip(query('SELECT seat FROM tickets WHERE category_id=?',[$c['id']])->fetchAll(PDO::FETCH_COLUMN));echo '<label>Seat<select required name="seat"><option value="">Select a seat</option>';for($i=1;$i<=$c['capacity'];$i++)if(!isset($taken[$i]))echo "<option>$i</option>";echo '</select></label>';}
?><button>Book one ticket</button></form><?php }?></section><?php }?></article></div><script src="assets/availability.js"></script><?php break;
case 'login':case 'register':
$reg=$p==='register';$next=(int)($_GET['event']??0);?><section class="auth panel"><div class="auth-art" aria-hidden="true"></div><div class="auth-content"><i class="bi bi-ticket-perforated auth-icon" aria-hidden="true"></i><h1><?=$reg?'Create account':'Welcome back'?></h1><?php formStart($reg?'register':'login');?><input type="hidden" name="event" value="<?=$next?>"><?php if($reg)inp('name','Full name');inp('email','Email address','','email');inp('password','Password','','password',$reg?'minlength="8" maxlength="72" autocomplete="new-password"':'autocomplete="current-password"');?><button><?=$reg?'Create account':'Sign in'?></button></form><p><a href="?p=<?=$reg?'login':'register'?>&event=<?=$next?>"><?=$reg?'Sign in to an existing account':'Create a participant account'?></a></p></div></section><?php break;
case 'mine': require __DIR__.'/../app/wallet.php'; break;
case 'ticket': require __DIR__.'/../app/pass.php'; break;
case 'admin':case 'edit':case 'reports':case 'check':require __DIR__.'/../app/management.php';break;
default:http_response_code(404);echo '<h1>Page not found</h1>';
}
ob_end_flush();}catch(Throwable $e){ob_end_clean();echo '<p class="notice error" role="alert">'.h(errorText($e)).'</p>';}
?></main><footer class="frame"><strong><i class="bi bi-ticket-perforated-fill"></i> Ticketdesk</strong><span>Event booking & admission</span></footer></body></html>

