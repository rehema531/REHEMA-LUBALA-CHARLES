<?php

$db = new SQLite3(__DIR__ . '/buses.db');

/* =========================
   DATABASE SETUP
========================= */
$db->exec("
CREATE TABLE IF NOT EXISTS buses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bus_number TEXT NOT NULL,
    company_name TEXT NOT NULL,
    origin TEXT NOT NULL,
    destination TEXT NOT NULL,
    departure TEXT NOT NULL,
    arrival TEXT NOT NULL,
    seats_available INTEGER NOT NULL,
    price REAL NOT NULL
);

CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bus_id INTEGER NOT NULL,
    passenger_name TEXT NOT NULL,
    seats INTEGER NOT NULL DEFAULT 1,
    booked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id)
);
");

/* =========================
   SEED DATA
========================= */
$count = $db->querySingle("SELECT COUNT(*) FROM buses");
if ($count == 0) {
    $db->exec("
        INSERT INTO buses
        (bus_number, company_name, origin, destination, departure, arrival, seats_available, price)
        VALUES
        ('BM101','Shabiby Line','Dar es Salaam','Morogoro','2026-06-15 06:00','2026-06-15 09:00',45,15000),
        ('BM202','Abood Bus','Morogoro','Dodoma','2026-06-15 10:30','2026-06-15 13:00',30,25000),
        ('BM303','New Force','Dar es Salaam','Mwanza','2026-06-16 07:00','2026-06-16 17:00',60,65000)
    ");
}

/* =========================
   MESSAGES
========================= */
$message = '';
$messageType = '';

/* =========================
   ACTIONS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* BOOK */
    if ($action === 'book') {

        $bus_id = (int)($_POST['bus_id'] ?? 0);
        $name   = trim($_POST['passenger_name'] ?? '');
        $seats  = max(1, (int)($_POST['seats'] ?? 1));

        if ($bus_id && $name) {

            $bus = $db->querySingle("SELECT * FROM buses WHERE id = $bus_id", true);

            if ($bus && $bus['seats_available'] >= $seats) {

                $stmt = $db->prepare("
                    INSERT INTO bookings (bus_id, passenger_name, seats)
                    VALUES (:bid, :name, :seats)
                ");
                $stmt->bindValue(':bid', $bus_id);
                $stmt->bindValue(':name', $name);
                $stmt->bindValue(':seats', $seats);
                $stmt->execute();

                $db->exec("
                    UPDATE buses
                    SET seats_available = seats_available - $seats
                    WHERE id = $bus_id
                ");

                $message = "Ticket booked! Bus {$bus['bus_number']} ({$bus['origin']} → {$bus['destination']}) for $name.";
                $messageType = 'success';

            } else {
                $message = 'Not enough seats available or invalid bus.';
                $messageType = 'error';
            }

        } else {
            $message = 'Please fill in all fields.';
            $messageType = 'error';
        }
    }

    /* CANCEL */
    if ($action === 'cancel') {

        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $booking = $db->querySingle("SELECT * FROM bookings WHERE id = $booking_id", true);

        if ($booking) {
            $db->exec("
                UPDATE buses
                SET seats_available = seats_available + {$booking['seats']}
                WHERE id = {$booking['bus_id']}
            ");

            $db->exec("DELETE FROM bookings WHERE id = $booking_id");

            $message = 'Booking cancelled successfully.';
            $messageType = 'success';
        }
    }
}

/* =========================
   FETCH DATA
========================= */
$buses = $db->query("SELECT * FROM buses ORDER BY departure");

$bookings = $db->query("
    SELECT b.*, 
           r.bus_number, r.origin, r.destination,
           r.departure, r.arrival, r.price
    FROM bookings b
    JOIN buses r ON b.bus_id = r.id
    ORDER BY b.booked_at DESC
");

$busRows = [];
while ($r = $buses->fetchArray(SQLITE3_ASSOC)) $busRows[] = $r;

$bookingRows = [];
while ($r = $bookings->fetchArray(SQLITE3_ASSOC)) $bookingRows[] = $r;

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bus Ticketing System</title>

<link rel="stylesheet" href="style.css">

</head>
<body>

<main>

<?php if ($message): ?>
<div class="toast <?= $messageType ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="tabs">
    <button class="tab-btn active" onclick="switchTab('buses',this)">Available Buses</button>
</div>

<!-- =========================
     BUS LIST
========================= -->
<div id="tab-buses" class="tab-panel active">

    <div class="section-label">Available Bus Routes</div>

    <div class="flight-grid">

    <?php foreach ($busRows as $b):

        $seats = (int)$b['seats_available'];
        $seatClass = $seats === 0 ? 'seats-none' : ($seats <= 10 ? 'seats-low' : 'seats-ok');
        $seatIcon = $seats === 0 ? '✖' : ($seats <= 10 ? '⚡' : '✓');

        $dep = new DateTime($b['departure']);
        $arr = new DateTime($b['arrival']);
        $diff = $dep->diff($arr);

        $dur = ($diff->h ? $diff->h.'h ' : '') . ($diff->i ? $diff->i.'m' : '');

        $originCode = strtoupper(substr(preg_replace('/[^A-Za-z]/','',$b['origin']),0,3));
        $destCode   = strtoupper(substr(preg_replace('/[^A-Za-z]/','',$b['destination']),0,3));
    ?>

    <div class="flight-card">

        <div class="card-top">
            <div>
                <div class="flight-num"><?= htmlspecialchars($b['bus_number']) ?></div>
                <div class="flight-date"><?= $dep->format('d M Y') ?></div>
            </div>

            <div class="flight-price-wrap">
                <span>Starting From</span>
                <div class="flight-price">
                    TZS <?= number_format($b['price'],2) ?>
                </div>
            </div>
        </div>

        <div class="flight-route">

            <div class="airport">
                <div class="airport-code"><?= $originCode ?></div>
                <div class="airport-city"><?= htmlspecialchars($b['origin']) ?></div>
                <div class="airport-time"><?= $dep->format('H:i') ?></div>
            </div>

            <div class="route-center">
                <div class="route-duration"><?= $dur ?></div>

                <div class="route-track">
                    <span class="plane">🚌</span>
                </div>

                <div class="route-type">EXPRESS BUS</div>
            </div>

            <div class="airport airport-right">
                <div class="airport-code"><?= $destCode ?></div>
                <div class="airport-city"><?= htmlspecialchars($b['destination']) ?></div>
                <div class="airport-time"><?= $arr->format('H:i') ?></div>
            </div>

        </div>

        <div class="flight-footer">

            <div class="seat-info">
                <span class="seats-badge <?= $seatClass ?>">
                    <?= $seatIcon ?> <?= $seats ?> Seats Left
                </span>
            </div>

            <div class="flight-meta">
                <?= $dep->format('d M') ?>
            </div>

        </div>

        <?php if ($seats > 0): ?>
        <button class="book-btn"
            onclick="openModal(
                <?= $b['id'] ?>,
                '<?= htmlspecialchars($b['bus_number']) ?>',
                '<?= htmlspecialchars($b['origin']) ?>',
                '<?= htmlspecialchars($b['destination']) ?>',
                <?= $b['price'] ?>,
                <?= $seats ?>
            )">
            BOOK NOW
        </button>
        <?php else: ?>
        <button class="book-btn" disabled>SOLD OUT</button>
        <?php endif; ?>

    </div>

    <?php endforeach; ?>

    </div>
</div>

<!-- =========================
     BOOKINGS
========================= -->
<div id="tab-bookings" class="tab-panel" style="margin-top:50px">

<div class="section-label">Confirmed Tickets</div>

<?php if (empty($bookingRows)): ?>
    <div class="empty-state">
        <p>No bookings yet.</p>
    </div>
<?php else: ?>

<div class="table-wrap">
<table>
<thead>
<tr>
    <th>Ref</th>
    <th>Bus</th>
    <th>Route</th>
    <th>Passenger</th>
    <th>Seats</th>
    <th>Total</th>
    <th>Departure</th>
    <th>Booked</th>
    <th></th>
</tr>
</thead>

<tbody>

<?php foreach ($bookingRows as $r):
$dep = new DateTime($r['departure']);
$booked = new DateTime($r['booked_at']);
?>

<tr>
    <td>#<?= str_pad($r['id'],5,'0',STR_PAD_LEFT) ?></td>
    <td><?= htmlspecialchars($r['bus_number']) ?></td>
    <td><?= htmlspecialchars($r['origin']) ?> → <?= htmlspecialchars($r['destination']) ?></td>
    <td><?= htmlspecialchars($r['passenger_name']) ?></td>
    <td><?= $r['seats'] ?></td>
    <td>TZS <?= number_format($r['price']*$r['seats'],2) ?></td>
    <td><?= $dep->format('d M Y H:i') ?></td>
    <td><?= $booked->format('d M H:i') ?></td>

    <td>
        <form method="POST" onsubmit="return confirm('Cancel booking #<?= $r['id'] ?>?')">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="booking_id" value="<?= $r['id'] ?>">
            <button class="cancel-btn">Cancel</button>
        </form>
    </td>
</tr>

<?php endforeach; ?>

</tbody>
</table>
</div>

<?php endif; ?>

</div>

</main>

<!-- =========================
     MODAL
========================= -->
<div class="modal-overlay" id="bookingModal" onclick="if(event.target===this)closeModal()">
<div class="modal">

<div class="modal-header">
    <div>
        <div class="modal-title">Book Bus Ticket</div>
        <div class="modal-route" id="modalRoute">—</div>
    </div>
    <button class="modal-close" onclick="closeModal()">✕</button>
</div>

<div class="modal-body">

<form method="POST">
<input type="hidden" name="action" value="book">
<input type="hidden" name="bus_id" id="modalBusId">

<div class="form-group">
    <label>Full Name</label>
    <input type="text" name="passenger_name" required>
</div>

<div class="form-group">
    <label>Seats</label>
    <select name="seats" id="modalSeats" onchange="updateTotal()"></select>
</div>

<div class="price-summary">
    <div id="priceBreakdown"></div>
    <div id="priceTotal"></div>
</div>

<button class="submit-btn">Confirm Ticket</button>

</form>

</div>
</div>
</div>

<script>
let currentPrice = 0;

function switchTab(tab, btn){
    document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));

    document.getElementById('tab-'+tab).classList.add('active');
    btn.classList.add('active');
}

function openModal(id,num,from,to,price,seats){
    currentPrice = price;

    document.getElementById('modalBusId').value = id;
    document.getElementById('modalRoute').textContent =
        num+' '+from+' → '+to;

    const sel = document.getElementById('modalSeats');
    sel.innerHTML='';

    for(let i=1;i<=Math.min(seats,9);i++){
        sel.innerHTML+=`<option value="${i}">${i} seat(s)</option>`;
    }

    updateTotal();
    document.getElementById('bookingModal').classList.add('open');
}

function closeModal(){
    document.getElementById('bookingModal').classList.remove('open');
}

function updateTotal(){
    const s = document.getElementById('modalSeats').value || 1;
    document.getElementById('priceTotal').innerText =
        'TZS ' + (s * currentPrice).toFixed(2);

    document.getElementById('priceBreakdown').innerText =
        s + ' × ' + currentPrice.toFixed(2);
}
</script>

</body>
</html>