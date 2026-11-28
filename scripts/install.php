<?php
require __DIR__.'/../app/db.php';
foreach(explode(';',file_get_contents(__DIR__.'/../app/schema.sql')) as $sql) if(trim($sql)) $db->exec($sql);
if(one('SELECT id FROM users LIMIT 1')) {
    require __DIR__.'/sample-events.php';
    return;
}
$db->beginTransaction();
$accounts=[];
foreach(['admin'=>'Administrator','staff'=>'Door staff'] as $role=>$name){
    $email=$role.'@ticketdesk.local';
    $password=bin2hex(random_bytes(10));
    query('INSERT INTO users(name,email,hash,role) VALUES(?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
    $accounts[]="$name   $email   $password";
}
$events=[
    ['After Hours Live','Music','Open Air Theatre, Colombo','An evening of live sets and new sounds under the open sky. Join the crowd for performances by local artists.','concert',14,1800],
    ['Design Assembly','Talks','Innovation Hall, Colombo','A gathering for curious minds. Meet designers and hear the stories behind their work.','conference',21,900],
    ['The Print Studio','Workshops','Community Arts Centre, Kandy','Explore colour and composition in a hands-on printmaking session. Materials are included.','art',28,1200],
];
foreach($events as [$title,$genre,$venue,$desc,$art,$days,$price]){
    query('INSERT INTO events(title,genre,venue,starts,description,art) VALUES(?,?,?,?,?,?)',[$title,$genre,$venue,date('Y-m-d 18:00:00',strtotime("+$days days")),$desc,$art]);
    $id=$db->lastInsertId();
    query('INSERT INTO categories(event_id,name,price,capacity,numbered) VALUES(?,?,?,?,?)',[$id,'General admission',$price,60,0]);
    query('INSERT INTO categories(event_id,name,price,capacity,numbered) VALUES(?,?,?,?,?)',[$id,'Reserved seating',$price+500,20,1]);
}
if(file_put_contents(ticketdesk_var_dir().'/local-accounts.txt',implode(PHP_EOL,$accounts))===false) throw new RuntimeException('Could not save local credentials');
$db->commit();
require __DIR__.'/sample-events.php';
