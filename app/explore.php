<?php
$now = date('Y-m-d H:i:s');
$q = trim($_GET['q'] ?? '');
$genre = $_GET['genre'] ?? '';
$sort = ($_GET['sort'] ?? '') === 'price' ? 'price' : 'date';
$events = query(
    'SELECT e.*, MIN(c.price) price FROM events e JOIN categories c ON c.event_id=e.id
     WHERE e.starts>? AND (e.title LIKE ? OR e.venue LIKE ?) AND (?="" OR e.genre=?)
     GROUP BY e.id ORDER BY '.($sort === 'price' ? 'price,e.starts' : 'e.starts').',e.id',
    [$now, "%$q%", "%$q%", $genre, $genre]
)->fetchAll();
$featured = query(
    'SELECT e.* FROM events e WHERE starts>? AND EXISTS
    (SELECT 1 FROM categories c WHERE c.event_id=e.id) ORDER BY starts,e.id LIMIT 3', [$now]
)->fetchAll();
$genres = query('SELECT DISTINCT genre FROM events e WHERE starts>? AND EXISTS
    (SELECT 1 FROM categories c WHERE c.event_id=e.id) ORDER BY genre', [$now])->fetchAll(PDO::FETCH_COLUMN);
?>
<?php if ($featured): ?>
<section class="spotlight" aria-label="Featured events">
    <?php foreach ($featured as $i => $event): ?>
    <article class="poster theme-<?=h($event['art'])?>" data-slide <?=$i ? 'hidden' : ''?>>
        <img class="poster-image" src="event-cover.php?id=<?=(int)$event['id']?>" alt="">
        <div class="poster-copy">
            <span class="kicker"><i class="bi bi-stars" aria-hidden="true"></i> In the spotlight</span>
            <h1><?=h($event['title'])?></h1>
            <div class="meta"><span><i class="bi bi-calendar3"></i> <?=h(date('D, j M', strtotime($event['starts'])))?></span>
            <span><i class="bi bi-geo-alt"></i> <?=h($event['venue'])?></span></div>
            <a class="button primary" href="?p=event&id=<?=$event['id']?>">View event <i class="bi bi-arrow-up-right"></i></a>
        </div>
    </article>
    <?php endforeach; ?>
    <?php if (count($featured)>1): ?>
    <div class="slide-controls" hidden>
        <button type="button" data-step="-1" aria-label="Previous featured event"><i class="bi bi-arrow-left"></i></button>
        <span aria-live="polite"><b data-current>01</b> / <?=sprintf('%02d',count($featured))?></span>
        <button type="button" data-step="1" aria-label="Next featured event"><i class="bi bi-arrow-right"></i></button>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<section class="explore" id="events" aria-labelledby="explore-title">
    <div class="catalog-head"><h2 id="explore-title">Explore events</h2>
    <form class="catalog-search" role="search" action="#events">
        <input type="hidden" name="genre" value="<?=h($genre)?>">
        <input type="hidden" name="sort" value="<?=h($sort)?>">
        <input name="q" type="search" aria-label="Search events or venues" placeholder="Search events or venues" value="<?=h($q)?>">
        <button aria-label="Search"><i class="bi bi-search"></i></button>
    </form></div>
    <div class="catalog-tools"><div class="chips" aria-label="Event categories">
        <?php foreach (array_merge([''],$genres) as $g):
            $icon = [''=>'grid','Music'=>'music-note-beamed','Talks'=>'mic','Workshops'=>'palette'][$g] ?? 'tag';
            $link = '?'.http_build_query(['genre'=>$g,'q'=>$q,'sort'=>$sort]).'#events'; ?>
            <a class="chip" href="<?=h($link)?>" <?=$g===$genre?'aria-current="true"':''?>><i class="bi bi-<?=$icon?>"></i><?=h($g?:'All events')?></a>
        <?php endforeach; ?>
    </div><form class="sort" action="#events">
        <input type="hidden" name="q" value="<?=h($q)?>"><input type="hidden" name="genre" value="<?=h($genre)?>">
        <i class="bi bi-arrow-down-up" aria-hidden="true"></i><select name="sort" aria-label="Sort events">
            <option value="date">Soonest first</option><option value="price" <?=$sort==='price'?'selected':''?>>Lowest price</option>
        </select><button type="submit">Apply</button>
    </form></div>
    <div class="cards">
        <?php foreach ($events as $event): $start=strtotime($event['starts']); ?>
        <a class="eventcard theme-<?=h($event['art'])?>" href="?p=event&id=<?=$event['id']?>">
            <div class="cover"><img src="event-cover.php?id=<?=(int)$event['id']?>" alt="">
                <span class="date"><small><?=date('M',$start)?></small><?=date('d',$start)?></span>
            </div>
            <div class="cardbody"><span class="genre"><?=h($event['genre'])?></span>
                <h3><?=h($event['title'])?></h3>
                <p><i class="bi bi-geo-alt"></i> <?=h($event['venue'])?></p>
                <div class="cardfoot"><span>From <strong><?=h(money($event['price']))?></strong></span><i class="bi bi-arrow-up-right"></i></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if (!$events): ?><div class="empty"><h3>No matching events</h3><a href="./#events">Clear filters</a></div><?php endif; ?>
</section>
<script src="assets/explore.js" defer></script>