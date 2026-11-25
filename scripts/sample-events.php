<?php
// One-time catalogue expansion for new and existing installations.
$db->exec('CREATE TABLE IF NOT EXISTS sample_batches (batch VARCHAR(80) PRIMARY KEY)');
$db->beginTransaction();
try {
    $added = query('INSERT IGNORE INTO sample_batches(batch) VALUES (?)', ['winter-programme-1'])->rowCount();
    if ($added) {
        $programme = [
            ['Courtyard Jazz Sessions', 'Music', 'Old Town Courtyard, Galle', 'concert', 18, '19:30', 'A quartet plays two acoustic sets in the courtyard, with a short interval. Courtyard passes include standing space. Choose a table seat for a numbered place close to the stage.', [['Courtyard standing', 1500, 80, 0], ['Table seat', 2400, 24, 1]]],
            ['Colour & Ink Lab', 'Workshops', 'Lakeview Art Room, Kandy', 'art', 24, '10:00', 'Build a two-colour print in a small studio group. Your morning session includes paper, inks and use of the press. Bring an apron and take your finished print home.', [['Studio place · materials included', 2200, 16, 1]]],
            ['City in Focus', 'Talks', 'Harbour Lecture Room, Colombo', 'conference', 32, '17:30', 'Three photographers share the stories behind their street portraits, followed by an audience discussion. The student category requires a student ID at the door.', [['Standard admission', 950, 72, 0], ['Student admission', 450, 24, 0]]],
        ];
        foreach ($programme as [$title,$genre,$venue,$art,$days,$time,$description,$categories]) {
            $starts = (new DateTimeImmutable("+$days days"))->format('Y-m-d').' '.$time.':00';
            query('INSERT INTO events(title,genre,venue,art,starts,description) VALUES(?,?,?,?,?,?)', [$title,$genre,$venue,$art,$starts,$description]);
            $event = $db->lastInsertId();
            foreach ($categories as $category) {
                query('INSERT INTO categories(event_id,name,price,capacity,numbered) VALUES(?,?,?,?,?)', [$event,...$category]);
            }
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}