<?php
function needRole(string ...$roles): void {
    if(!in_array($_SESSION['user']['role']??'', $roles,true)) {
        http_response_code(403); throw new DomainException('This page requires an authorized account.');
    }
}
function go(string $url): never {header('Location: '.$url,true,303);exit;}
function field(string $key,int $max): string {
    $value=trim($_POST[$key]??'');
    if($value==='' || mb_strlen($value)>$max) throw new DomainException("Check the ".str_replace('_',' ',$key)." field.");
    return $value;
}
function action(string $a): string {
    global $db;
    if(!hash_equals($_SESSION['csrf'],$_POST['csrf']??'')) throw new DomainException('Your form expired. Refresh the page and try again.');
    $id=(int)($_POST['id']??0);
    if($a==='register'||$a==='login'){
        $email=field('email',190);$pass=$_POST['password']??'';
        if($a==='register'){
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<8||strlen($pass)>72) throw new DomainException('Enter a valid email and a password between 8 and 72 bytes.');
            query('INSERT INTO users(name,email,hash) VALUES(?,?,?)',[field('name',100),$email,password_hash($pass,PASSWORD_DEFAULT)]);
        }
        $u=one('SELECT * FROM users WHERE email=?',[$email]);
        if(!$u||!password_verify($pass,$u['hash'])) throw new DomainException('Email or password is incorrect.');
        unset($u['hash']);session_regenerate_id(true);$_SESSION['user']=$u;$_SESSION['csrf']=bin2hex(random_bytes(24));
        $next=(int)($_POST['event']??0);
        return $next?'?p=event&id='.$next:match($u['role']){'admin'=>'?p=admin','staff'=>'?p=check',default=>'?p=home'};
    }
    if($a==='logout'){$_SESSION=[];session_regenerate_id(true);return '?p=home';}
    if($a==='book'){
        needRole('participant');
        $key=$_POST['request_key']??'';
        if(!preg_match('/^[a-f0-9]{32}$/D',$key)) throw new DomainException('Please reopen the booking form.');
        $db->beginTransaction();
        $c=one('SELECT c.*,e.starts FROM categories c JOIN events e ON e.id=c.event_id WHERE c.id=? AND e.id=? FOR UPDATE',[(int)($_POST['category']??0),$id]);
        $prior=one('SELECT id FROM bookings WHERE request_key=? AND user_id=?',[$key,$_SESSION['user']['id']]);
        if($prior){$db->commit();return '?p=ticket&id='.$prior['id'];}
        if(!$c||$c['starts']<=date('Y-m-d H:i:s')) throw new DomainException('Booking is closed.');
        $count=one('SELECT COUNT(*) n FROM bookings WHERE category_id=?',[$c['id']])['n'];
        if($count >= $c['capacity']) throw new DomainException('This category is sold out.');
        $seat=$c['numbered']?(int)($_POST['seat']??0):null;
        if($c['numbered']&&($seat<1||$seat>$c['capacity']||one('SELECT id FROM tickets WHERE category_id=? AND seat=?',[$c['id'],$seat]))) throw new DomainException('Choose an available seat.');
        query('INSERT INTO bookings(user_id,category_id,created,request_key) VALUES(?,?,?,?)',[$_SESSION['user']['id'],$c['id'],date('Y-m-d H:i:s'),$key]);
        $bid=$db->lastInsertId();
        query('INSERT INTO payments(booking_id,amount,paid_at) VALUES(?,?,?)',[$bid,$c['price'],(float)$c['price']===0.0?date('Y-m-d H:i:s'):null]);
        query('INSERT INTO tickets(booking_id,category_id,seat,code) VALUES(?,?,?,?)',[$bid,$c['id'],$seat,strtoupper(bin2hex(random_bytes(12)))]);
        $db->commit();return '?p=ticket&id='.$bid;
    }
    if($a==='admit'){
        needRole('admin','staff');
        $db->beginTransaction();
        $t=one('SELECT t.id,p.paid_at FROM tickets t JOIN payments p ON p.booking_id=t.booking_id JOIN categories c ON c.id=t.category_id WHERE t.code=? AND c.event_id=? FOR UPDATE',[strtoupper(field('code',80)),$id]);
        if(!$t) throw new DomainException('Ticket not found for this event.');
        if(one('SELECT ticket_id FROM attendance WHERE ticket_id=?',[$t['id']])) throw new DomainException('Already checked in. This ticket has been used.');
        if(!$t['paid_at']) throw new DomainException('Payment is pending. Confirm payment with the organizer.');
        query('INSERT INTO attendance VALUES(?,?,?)',[$t['id'],$_SESSION['user']['id'],date('Y-m-d H:i:s')]);$db->commit();
        $_SESSION['message']='Ticket accepted. Attendance recorded.';return '?p=check&id='.$id;
    }
    needRole('admin');
    if($a==='paid'){query('UPDATE payments SET paid_at=COALESCE(paid_at,?) WHERE booking_id=?',[date('Y-m-d H:i:s'),$id]);return '?p=reports';}
    if($a==='delete_event'){
        if(one('SELECT b.id FROM bookings b JOIN categories c ON c.id=b.category_id WHERE c.event_id=? LIMIT 1',[$id])) throw new DomainException('This event has bookings and must be kept for reporting.');
        query('DELETE FROM events WHERE id=?',[$id]);return '?p=admin';
    }
    if($a==='event'){
        $date=DateTime::createFromFormat('!Y-m-d\\TH:i',$_POST['starts']??'');
        if(!$date||$date->format('Y-m-d\\TH:i')!==$_POST['starts']) throw new DomainException('Choose a valid event date and time.');
        $art=field('art',20);if(!in_array($art,['concert','conference','art'],true)) throw new DomainException('Choose an event image.');
        $data=[field('title',150),field('genre',40),field('venue',160),$date->format('Y-m-d H:i:s'),field('description',5000),$art];
        $cover = uploadedCover();
        $db->beginTransaction();
        if($id) query('UPDATE events SET title=?,genre=?,venue=?,starts=?,description=?,art=? WHERE id=?',[...$data,$id]);
        else {query('INSERT INTO events(title,genre,venue,starts,description,art) VALUES(?,?,?,?,?,?)',$data);$id=$db->lastInsertId();}
        if ($cover !== null) {
            query('INSERT INTO event_covers(event_id,jpeg) VALUES(?,?) ON DUPLICATE KEY UPDATE jpeg=?', [$id,$cover,$cover]);
        } elseif (isset($_POST['remove_cover'])) {
            query('DELETE FROM event_covers WHERE event_id=?', [$id]);
        }
        $db->commit();
        return '?p=edit&id='.$id;
    }
    if($a==='category'){
        $cid=(int)($_POST['category']??0);
        $cap=filter_var($_POST['capacity']??'',FILTER_VALIDATE_INT);
        $price=field('price',12);$name=field('name',80);$numbered=isset($_POST['numbered'])?1:0;
        if(!$cap||$cap<1||$cap>10000||!preg_match('/^\d{1,8}(\.\d{1,2})?$/D',$price)) throw new DomainException('Use a capacity from 1 to 10,000 and a valid nonnegative price.');
        $db->beginTransaction();
        if($cid){
            $old=one('SELECT * FROM categories WHERE id=? AND event_id=? FOR UPDATE',[$cid,$id]);
            $sold=one('SELECT COUNT(*) n,MAX(seat) highest FROM tickets WHERE category_id=?',[$cid]);
            if(!$old||$cap<max($sold['n'],$sold['highest']??0)||($sold['n']&&$numbered!=$old['numbered'])) throw new DomainException('Capacity or seating conflicts with issued tickets.');
            query('UPDATE categories SET name=?,price=?,capacity=?,numbered=? WHERE id=?',[$name,$price,$cap,$numbered,$cid]);
        }else query('INSERT INTO categories(event_id,name,price,capacity,numbered) VALUES(?,?,?,?,?)',[$id,$name,$price,$cap,$numbered]);
        $db->commit();return '?p=edit&id='.$id;
    }
    throw new DomainException('Unknown action.');
}