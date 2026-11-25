<?php
needRole('participant');
$items = query(LEDGER.' WHERE b.user_id=? ORDER BY b.id DESC', [$user['id']])->fetchAll();
function walletCard(array $t, bool $preview = false): void {
    $start = strtotime($t['starts']);
    $link = $preview ? '?p=event&id='.$t['event_id'] : '?p=ticket&id='.$t['id'];
    ?>
    <article class="pass-row theme-<?=h($t['art'])?>">
        <div class="pass-photo">
            <img src="event-cover.php?id=<?=(int)$t['event_id']?>" alt="" loading="lazy">
            <time datetime="<?=date('Y-m-d',$start)?>"><small><?=date('M',$start)?></small><?=date('d',$start)?></time>
        </div>
        <div class="pass-summary">
            <div class="pass-tags"><span class="genre"><?=h($t['genre'])?></span>
            <?php if (!$preview): ?><span class="pay-state <?=$t['paid_at']?'settled':''?>"><?= $t['paid_at']?'Paid':'Payment pending' ?></span><?php endif; ?></div>
            <h3><a href="<?=h($link)?>"><?=h($t['title'])?></a></h3>
            <p><i class="bi bi-calendar3"></i> <?=h(stamp($t['starts']))?></p>
            <p><i class="bi bi-geo-alt"></i> <?=h($t['venue'])?></p>
            <p><i class="bi bi-ticket-perforated"></i> <?=h($t['category'])?><?=!empty($t['seat'])?' · Seat '.(int)$t['seat']:''?></p>
        </div>
        <div class="pass-stub">
            <small><?= $preview?'Ticket price':'Booking '.h(ticketReference($t['id'])) ?></small>
            <strong><?=h(money($t['amount']))?></strong>
            <a class="button" href="<?=h($link)?>"><?=$preview?'Explore event':'View ticket'?> <i class="bi bi-arrow-up-right"></i></a>
        </div>
    </article>
    <?php
}
?>
<div class="wallet-heading"><h1>My tickets</h1><span><?=count($items)?> <?=count($items)===1?'booking':'bookings'?></span></div>
<?php if (!$items): ?><div class="empty"><h3>Your ticket wallet is empty</h3><a href="./#events">Find your first event</a></div><?php endif; ?>
<div class="pass-list"><?php foreach ($items as $item) walletCard($item); ?></div>
<?php
$samples = query('SELECT e.id event_id,e.title,e.venue,e.starts,e.art,e.genre,c.name category,c.price amount
    FROM events e JOIN categories c ON c.id=(SELECT MIN(c2.id) FROM categories c2 WHERE c2.event_id=e.id)
    WHERE e.starts>? AND NOT EXISTS(SELECT 1 FROM bookings b JOIN categories bc ON bc.id=b.category_id
    WHERE b.user_id=? AND bc.event_id=e.id) ORDER BY e.starts,e.id LIMIT 2',[date('Y-m-d H:i:s'),$user['id']])->fetchAll();
if ($samples): ?>
<section class="wallet-samples"><h2>Sample tickets</h2><p>Preview ticket options for other events.</p>
<div class="pass-list"><?php foreach ($samples as $sample) walletCard($sample,true); ?></div></section>
<?php endif; ?>