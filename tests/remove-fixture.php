<?php
// Remove only rows owned by the generated test users and their matching test event.
require __DIR__.'/../app/db.php';
[$script,$key,$event]=$argv;
if(!preg_match('/^[a-f0-9]{12}$/D',$key))exit(1);
$db->beginTransaction();
if (!one('SELECT id FROM events WHERE id=? AND title=? FOR UPDATE',[$event,'QA '.$key])) exit(1);
$users=[$key.'-0@test.invalid',$key.'-1@test.invalid'];
$ids=query('SELECT b.id FROM bookings b JOIN users u ON u.id=b.user_id JOIN categories c ON c.id=b.category_id WHERE c.event_id=? AND u.email IN (?,?)',[$event,...$users])->fetchAll(PDO::FETCH_COLUMN);
foreach($ids as $id){
    query('DELETE a FROM attendance a JOIN tickets t ON t.id=a.ticket_id WHERE t.booking_id=?',[$id]);
    query('DELETE FROM tickets WHERE booking_id=?',[$id]);
    query('DELETE FROM payments WHERE booking_id=?',[$id]);
    query('DELETE FROM bookings WHERE id=?',[$id]);
}
query('DELETE FROM events WHERE id=?',[$event]);
query('DELETE FROM users WHERE email IN (?,?)',$users);
$db->commit();