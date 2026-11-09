
<?php
require_once 'config.php';

// Protect dashboard
if (empty($_SESSION['access_token'])) {
    header('Location: login.php');
    exit;
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Fetch login attempts from Supabase

$suspicious = supabase_request(
    'GET',
    '/rest/v1/suspicious_ips?select=*&order=failed_attempts.desc',
    null,
    $_SESSION['access_token']
);

if (!is_array($suspicious) || isset($suspicious['message'])) {
    $suspicious = [];
}


// Validate response
if (!is_array($attempts) || isset($attempts['message'])) {
    $attempts = [];
}

// Dashboard statistics
$total = count($attempts);

$failed = 0;
$successful = 0;
$locations = [];

foreach ($attempts as $attempt) {
    if (($attempt['status'] ?? '') === 'failed') {
        $failed++;
    }

    if (($attempt['status'] ?? '') === 'success') {
        $successful++;
    }

    $lat = $attempt['latitude'] ?? null;
    $lng = $attempt['longitude'] ?? null;

    if (
        is_numeric($lat) &&
        is_numeric($lng) &&
        $lat >= -90 && $lat <= 90 &&
        $lng >= -180 && $lng <= 180
    ) {
        $locations[] = [
            'username' => $attempt['username'] ?? 'Unknown',
            'ip' => $attempt['ip_address'] ?? 'Unknown',
            'city' => $attempt['city'] ?? 'Unknown',
            'region' => $attempt['region'] ?? 'Unknown',
            'country' => $attempt['country'] ?? 'Unknown',
            'status' => $attempt['status'] ?? 'Unknown',
            'latitude' => (float)$lat,
            'longitude' => (float)$lng,
            'date' => $attempt['attempted_at'] ?? ''
        ];
    }
}

$locationJson = json_encode(
    $locations,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

$username = $_SESSION['username'] ?? 'Administrator';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Brute Force Protection Dashboard</title>

<link rel="stylesheet"
href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #0b1120;
    color: #e5e7eb;
    font-family: Arial, sans-serif;
}

.sidebar {
    position: fixed;
    width: 240px;
    height: 100vh;
    background: #111827;
    padding: 25px 18px;
}

.logo {
    color: #60a5fa;
    font-size: 21px;
    font-weight: bold;
    margin-bottom: 35px;
}

.sidebar a {
    display: block;
    padding: 13px;
    margin-bottom: 9px;
    border-radius: 8px;
    color: #cbd5e1;
    text-decoration: none;
}

.sidebar a:hover,
.sidebar a.active {
    background: #1e3a5f;
    color: white;
}

.main {
    margin-left: 240px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 30px;
}

.topbar h1 {
    margin: 0;
    font-size: 27px;
}

.muted {
    color: #94a3b8;
}

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: #172235;
    border: 1px solid #29364a;
    padding: 22px;
    border-radius: 12px;
}

.card h3 {
    color: #94a3b8;
    font-size: 14px;
    font-weight: normal;
}

.number {
    font-size: 32px;
    font-weight: bold;
    margin-top: 10px;
}

.blue { color: #60a5fa; }
.red { color: #f87171; }
.green { color: #4ade80; }

.panel {
    background: #172235;
    border: 1px solid #29364a;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 25px;
}

.panel h2 {
    margin-top: 0;
}

#map {
    width: 100%;
    height: 430px;
    border-radius: 10px;
    z-index: 1;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th, td {
    padding: 14px;
    border-bottom: 1px solid #334155;
    text-align: left;
    font-size: 13px;
}

th {
    color: #60a5fa;
    background: #1e293b;
}

tr:hover {
    background: #1e293b;
}

.status {
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.status.success {
    color: #4ade80;
    background: #14532d;
}

.status.failed {
    color: #f87171;
    background: #7f1d1d;
}

.logout {
    background: #991b1b;
    color: white !important;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #94a3b8;
}

@media(max-width: 850px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }

    .cards {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">🛡️ BF PROTECTION</div>

    <a href="index.php" class="active">📊 Dashboard</a>
    <a href="#attempts">🔐 Login Attempts</a>
    <a href="#location">🌍 Location Map</a>
    <a href="logout.php" class="logout">🚪 Logout</a>

</aside>

<main class="main">

    <div class="topbar">
        <div>
            <h1>Security Dashboard</h1>
            <p class="muted">Welcome, <?= h($username) ?></p>
        </div>

        <div class="muted">
            Brute Force Monitoring System
        </div>
    </div>

    <!-- STATISTICS -->

    <section class="cards">

        <div class="card">
            <h3>Total Login Attempts</h3>
            <div class="number blue"><?= $total ?></div>
        </div>

        <div class="card">
            <h3>Failed Login Attempts</h3>
            <div class="number red"><?= $failed ?></div>
        </div>

        <div class="card">
            <h3>Successful Logins</h3>
            <div class="number green"><?= $successful ?></div>
        </div>

<section class="panel">
    <h2>🚨 Suspicious IP Addresses</h2>
    <p class="muted">
        IP addresses with at least 5 failed attempts
        within the last 15 minutes.
    </p>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Failed Attempts</th>
                    <th>Last Attempt</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($suspicious)): ?>
                <tr>
                    <td colspan="4" class="empty">
                        No suspicious IP addresses found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($suspicious as $item): ?>
                    <tr>
                        <td><?= h($item['ip_address'] ?? '') ?></td>
                        <td><?= h($item['failed_attempts'] ?? 0) ?></td>
                        <td><?= h($item['last_attempt'] ?? '') ?></td>
                        <td>
                            <span class="status failed">
                                SUSPICIOUS
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

    </section>

    <!-- LOCATION MAP -->

    <section class="panel" id="location">

        <h2>🌍 Login Location Tracking</h2>

        <p class="muted">
            Approximate locations based on recorded IP geolocation.
        </p>

        <div id="map"></div>

        <p class="muted">
            Locations may be inaccurate and do not identify an exact address.
        </p>

    </section>

    <!-- LOGIN ATTEMPTS -->

    <section class="panel" id="attempts">

        <h2>🔐 Recent Login Attempts</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Username</th>
                        <th>IP Address</th>
                        <th>Device</th>
                        <th>Browser</th>
                        <th>City</th>
                        <th>Region</th>
                        <th>Country</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($attempts)): ?>

                    <tr>
                        <td colspan="8" class="empty">
                            No login attempts recorded yet.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($attempts as $attempt): ?>

                    <tr>

                        <td><?= h($attempt['username'] ?? 'Unknown') ?></td>

                        <td><?= h($attempt['ip_address'] ?? 'Unknown') ?></td>

                        <td><?= h($attempt['device'] ?? 'Unknown') ?></td>

                        <td><?= h($attempt['browser'] ?? 'Unknown') ?></td>

                        <td><?= h($attempt['city'] ?? 'Unknown') ?></td>

                        <td><?= h($attempt['country'] ?? 'Unknown') ?></td>

                        <td><?= h($attempt['region'] ?? 'Unknown') ?></td>

                        <td>
                            <?php
                            $status = strtolower($attempt['status'] ?? 'unknown');
                            ?>

                            <span class="status <?= h($status) ?>">
                                <?= h(strtoupper($status)) ?>
                            </span>
                        </td>

                        <td><?= h($attempt['attempted_at'] ?? '') ?></td>

                    </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

<script>
const locations = <?= $locationJson ?: '[]' ?>;

const map = L.map('map').setView([12.8797, 121.7740], 5);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

const markerPoints = [];

locations.forEach(location => {

    const marker = L.marker([
        location.latitude,
        location.longitude
    ]).addTo(map);

    const popup = `
        <b>Login Activity</b><br>
        User: ${escapeHTML(location.username)}<br>
        IP: ${escapeHTML(location.ip)}<br>
        City: ${escapeHTML(location.city)}<br>
        Country: ${escapeHTML(location.country)}<br>
        Status: ${escapeHTML(location.status)}<br>
        Date: ${escapeHTML(location.date)}
    `;

    marker.bindPopup(popup);

    markerPoints.push([
        location.latitude,
        location.longitude
    ]);
});

function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[char]);
}

if (markerPoints.length > 0) {
    map.fitBounds(markerPoints, {padding: [30, 30]});
}
</script>
    <script>
    // Automatic refresh every 10 seconds
    setInterval(() => {
        window.location.reload();
    }, 10000);
    </script>

</body>
</html>
