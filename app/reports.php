<?php
const LEDGER='SELECT b.id,b.user_id,u.name,u.email,e.id event_id,e.title,e.art,e.genre,e.venue,e.starts,c.name category,t.code,t.seat,p.amount,p.paid_at,a.checked,b.created FROM bookings b JOIN users u ON u.id=b.user_id JOIN categories c ON c.id=b.category_id JOIN events e ON e.id=c.event_id JOIN tickets t ON t.booking_id=b.id JOIN payments p ON p.booking_id=b.id LEFT JOIN attendance a ON a.ticket_id=t.id';
function qr(string $code): string {
    $renderer=new BaconQrCode\Renderer\ImageRenderer(new BaconQrCode\Renderer\RendererStyle\RendererStyle(240),new BaconQrCode\Renderer\Image\SvgImageBackEnd());
    return (new BaconQrCode\Writer($renderer))->writeString($code);
}
function pdfFile(string $title,string $body): never {
    $pdf=new Dompdf\Dompdf();$pdf->loadHtml('<meta charset="utf-8"><style>body{font:12px DejaVu Sans;color:#182632}h1{font-size:23px}table{border-collapse:collapse;width:100%}th,td{padding:6px;border-bottom:1px solid #ccc;text-align:left}</style><h1>'.h($title).'</h1>'.$body);
    $pdf->setPaper('A4','landscape');$pdf->render();$pdf->stream('Ticketdesk-'.$title.'.pdf');exit;
}
function ticketFor(int $id): array {
    needRole('admin','participant');
    $t=one(LEDGER.' WHERE b.id=?',[$id]);
    if(!$t||($_SESSION['user']['role']!=='admin'&&$t['user_id']!=$_SESSION['user']['id'])) throw new DomainException('Ticket unavailable.');
    return $t;
}
function reportRows(int $event): array {
    return query(LEDGER.($event?' WHERE e.id=?':'').' ORDER BY b.id DESC',$event?[$event]:[])->fetchAll();
}
function exportReport(int $event,string $format): never {
    needRole('admin');$rows=[['Booking','Event','Participant','Category','Seat','Amount (LKR)','Paid on','Checked in']];
    foreach(reportRows($event) as $r) $rows[]=[$r['id'],$r['title'],$r['name'],$r['category'],$r['seat']??'General',(float)$r['amount'],$r['paid_at']??'Pending',$r['checked']??'Absent'];
    if($format==='xlsx'){
        foreach($rows as &$row) foreach($row as &$cell) if(is_string($cell)) $cell=Shuchkin\SimpleXLSXGen::raw($cell);
        unset($row,$cell);
        Shuchkin\SimpleXLSXGen::fromArray($rows)->downloadAs('event-report.xlsx');exit;
    }
    $html='<table>';foreach($rows as $row) $html.='<tr><td>'.implode('</td><td>',array_map('h',$row)).'</td></tr>';
    pdfFile('Event report',$html.'</table>');
}
function ticketReference(int $id): string {
    return sprintf('TD-%06d', $id);
}

function ticketFacts(array $t): array {
    return [
        'Ticket holder' => $t['name'],
        'Booking reference' => ticketReference($t['id']),
        'Event time (Sri Lanka)' => stamp($t['starts']),
        'Venue' => $t['venue'],
        'Ticket category' => $t['category'],
        'Seating' => $t['seat'] ? 'Seat '.$t['seat'] : 'Unassigned',
        'Ticket price' => money($t['amount']),
        'Payment' => $t['paid_at'] ? 'Paid · '.stamp($t['paid_at']) : 'Pending',
        'Booked on' => stamp($t['created']),
        'Admission' => $t['checked'] ? 'Checked in · '.stamp($t['checked']) : 'Awaiting check-in',
    ];
}

function downloadTicket(array $t): never {
    $cover = one('SELECT jpeg FROM event_covers WHERE event_id=?', [$t['event_id']]);
    $photo = $cover['jpeg'] ?? file_get_contents(__DIR__.'/../public/assets/'.$t['art'].'.jpg');
    [$width, $height] = getimagesizefromstring($photo);
    $thumbWidth = max(1, (int)round(min(120, 110 * $width / $height)));
    $qr = base64_encode(qr($t['code']));
    $body = '<table><tr><td width="125"><img width="'.$thumbWidth.'" src="data:image/jpeg;base64,'.base64_encode($photo).'"></td>'
        .'<td><h2>'.h($t['title']).'</h2><p>'.h($t['genre'].' · '.$t['venue']).'</p></td>'
        .'<td align="center" rowspan="2"><img width="158" src="data:image/svg+xml;base64,'.$qr.'"><p>'.h($t['code']).'</p></td></tr>'
        .'<tr><td colspan="2"><table>';
    foreach (ticketFacts($t) as $label=>$value) {
        $body .= '<tr><td>'.h($label).'</td><td>'.h($value).'</td></tr>';
    }
    pdfFile('Ticket '.$t['id'], $body.'</table></td></tr></table>');
}
