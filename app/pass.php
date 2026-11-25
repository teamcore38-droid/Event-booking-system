<?php
$t = ticketFor($id);
?>
<a class="back" href="<?= $role==='admin'?'?p=reports':'?p=mine' ?>">← <?= $role==='admin'?'Bookings & reports':'My tickets' ?></a>
<article class="admission theme-<?=h($t['art'])?>">
    <div class="admission-body">
        <div class="admission-head"><img src="event-cover.php?id=<?=(int)$t['event_id']?>" alt=""><div>
            <span class="eyebrow">Confirmed ticket #<?=$id?></span>
            <h1><?=h($t['title'])?></h1><span class="genre"><?=h($t['genre'])?></span>
        </div></div>
        <dl class="pass-fields">
            <?php foreach (ticketFacts($t) as $label=>$value): ?>
            <div><dt><?=h($label)?></dt><dd><?=h($value)?></dd></div>
            <?php endforeach; ?>
        </dl>
    </div>
    <aside class="entry-code"><h2>Entry pass</h2>
        <img width="180" height="180" alt="Ticket QR code" src="data:image/svg+xml;base64,<?=base64_encode(qr($t['code']))?>">
        <p><code><?=h($t['code'])?></code></p>
        <p><?= $t['checked']?'Admission recorded.':($t['paid_at']?'Present this code at the entrance.':'Pay the organizer before check-in.') ?></p>
        <a class="button" href="?p=download&id=<?=$id?>"><i class="bi bi-download"></i> Download PDF ticket</a>
    </aside>
</article>