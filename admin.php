<?php

date_default_timezone_set('Asia/Kolkata');

session_start();

/* ======================
   AUTH CHECK
====================== */

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (!($_SESSION['admin_logged_in'] ?? false)) {
    header('Location: login.php');
    exit;
}


/* ======================
   CONFIGURATION
====================== */

$usersFile = __DIR__ . '/data/users.json';
$presenceFile = __DIR__ . '/data/presence.json';
$feedbackFile = __DIR__ . '/data/feedback/feedback.json';

$OFFLINE_AFTER = 10;


/* ======================
   LOAD JSON FILE
====================== */

function loadJsonFile($file)
{
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    if ($content === false || trim($content) === '') {
        return [];
    }
    $data = json_decode($content, true);
    if (!is_array($data)) {
        return [];
    }
    return $data;
}


/* ======================
   HTML ESCAPE
====================== */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/* ======================
   GET TIMESTAMP
====================== */

function getLastSeenTimestamp($value)
{
    if ($value === null || $value === '') {
        return 0;
    }
    if (is_numeric($value)) {
        return (int)$value;
    }
    $timestamp = strtotime($value);
    return ($timestamp !== false) ? $timestamp : 0;
}


/* ======================
   FORMAT DATE TIME
====================== */

function formatLastSeen($value)
{
    if ($value === null || $value === '' || $value === 0) {
        return '-';
    }
    $timestamp = getLastSeenTimestamp($value);
    if ($timestamp === 0) {
        return '-';
    }
    return date('d-m-Y H:i:s', $timestamp);
}


/* ======================
   BUILD USER DATA (for normal mode)
====================== */

function buildUserData($users, $presence, $offlineAfter)
{
    $result = [];

    // 1. Users from users.json
    foreach ($users as $key => $user) {
        if (!is_array($user)) continue;
        $number = $user['number'] ?? (string)$key;
        $result[$number] = [
            'number'      => $number,
            'name'        => $user['name'] ?? '',
            'password'    => $user['password'] ?? '',
            'time'        => $user['time'] ?? '',
            'page'        => '',
            'conversation'=> '',
            'last_seen'   => '',
            'last_seen_ts'=> 0,
            'last_seen_formatted' => '-',
            'status'      => 'OFFLINE'
        ];
    }

    // 2. Presence data
    foreach ($presence as $key => $pre) {
        if (!is_array($pre)) continue;
        $number = $pre['number'] ?? (string)$key;
        if (!isset($result[$number])) {
            $result[$number] = [
                'number'      => $number,
                'name'        => $pre['name'] ?? '',
                'password'    => '',
                'time'        => '',
                'page'        => '',
                'conversation'=> '',
                'last_seen'   => '',
                'last_seen_ts'=> 0,
                'last_seen_formatted' => '-',
                'status'      => 'OFFLINE'
            ];
        }
        if (isset($pre['name'])) $result[$number]['name'] = $pre['name'];
        if (isset($pre['page'])) $result[$number]['page'] = $pre['page'];
        if (isset($pre['conversation'])) $result[$number]['conversation'] = $pre['conversation'];
        if (isset($pre['last_seen'])) {
            $result[$number]['last_seen'] = $pre['last_seen'];
            $timestamp = getLastSeenTimestamp($pre['last_seen']);
            $result[$number]['last_seen_ts'] = $timestamp;
            $result[$number]['last_seen_formatted'] = formatLastSeen($pre['last_seen']);
            $result[$number]['status'] = ($timestamp > 0 && (time() - $timestamp) <= $offlineAfter) ? 'ONLINE' : 'OFFLINE';
        }
    }

    return array_values($result);
}


/* ==========================================================
   AJAX LIVE DATA REQUEST
========================================================== */

if (isset($_GET['ajax']) && $_GET['ajax'] === 'users') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $users = loadJsonFile($usersFile);
    $presence = loadJsonFile($presenceFile);
    $feedback = loadJsonFile($feedbackFile);

    $allUsers = buildUserData($users, $presence, $OFFLINE_AFTER);

    $total = count($allUsers);
    $online = 0;
    $offline = 0;
    foreach ($allUsers as $user) {
        if ($user['status'] === 'ONLINE') $online++;
        else $offline++;
    }

    // Return both user data and raw feedback list
    echo json_encode([
        'success'     => true,
        'server_time' => date('Y-m-d H:i:s'),
        'total'       => $total,
        'online'      => $online,
        'offline'     => $offline,
        'users'       => $allUsers,
        'feedback'    => $feedback   // raw feedback entries
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


/* ==========================================================
   INITIAL PAGE DATA
========================================================== */

$users = loadJsonFile($usersFile);
$presence = loadJsonFile($presenceFile);
$feedback = loadJsonFile($feedbackFile);   // raw feedback array

$allUsers = buildUserData($users, $presence, $OFFLINE_AFTER);

$totalUsers = count($allUsers);
$onlineUsers = 0;
$offlineUsers = 0;
foreach ($allUsers as $user) {
    if ($user['status'] === 'ONLINE') $onlineUsers++;
    else $offlineUsers++;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Admin - Users Chat Records</title>
<link rel="icon" type="image/png" href="../images/hhh%20picture.png">
<style>
/* ==========================================================
   RESET
========================================================== */
* { box-sizing: border-box; }

/* ==========================================================
   BODY
========================================================== */
body {
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(135deg, #eef4ff, #f8fafc);
    color: #172033;
    min-height: 100vh;
}

/* ==========================================================
   HEADER
========================================================== */
.header {
    position: sticky;
    top: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 15px 22px;
    background: rgba(255,255,255,.96);
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 4px 20px rgba(0,0,0,.06);
}
.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.logo {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    font-size: 23px;
    box-shadow: 0 7px 18px rgba(37,99,235,.25);
}
.title {
    margin: 0;
    font-size: 21px;
    font-weight: 800;
}
.subtitle {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 12px;
}
.logout {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
    border-radius: 10px;
    background: #fee2e2;
    color: #b91c1c;
    text-decoration: none;
    font-weight: bold;
    font-size: 13px;
    transition: .2s;
}
.logout:hover {
    background: #fecaca;
    transform: translateY(-1px);
}

/* ==========================================================
   MAIN
========================================================== */
.main {
    width: 96%;
    max-width: 1600px;
    margin: 20px auto;
}

/* ==========================================================
   STATISTICS
========================================================== */
.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 18px;
}
.stat {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 17px;
    box-shadow: 0 7px 25px rgba(15,23,42,.05);
}
.stat-label {
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}
.stat-value {
    margin-top: 7px;
    font-size: 27px;
    font-weight: 800;
}
.total { color: #2563eb; }
.online { color: #16a34a; }
.offline { color: #dc2626; }
.refresh-time { color: #7c3aed; font-size: 16px; }

/* ==========================================================
   CONTROLS
========================================================== */
.controls {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    margin-bottom: 15px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 7px 25px rgba(15,23,42,.05);
    flex-wrap: wrap;
}

.search-container {
    flex: 1;
    position: relative;
}
.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
}
#admin_search {
    width: 100%;
    padding: 12px 15px 12px 42px;
    border: 1px solid #cbd5e1;
    border-radius: 11px;
    outline: none;
    font-size: 14px;
    background: white;
}
#admin_search:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37,99,235,.10);
}

.live {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: 999px;
    background: #ecfdf5;
    color: #15803d;
    font-size: 12px;
    font-weight: 800;
}
.live-dot {
    width: 8px;
    height: 8px;
    background: #22c55e;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
    70% { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
    100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
}

/* Feedback toggle button */
.toggle-feedback {
    border: none;
    padding: 12px 17px;
    border-radius: 11px;
    background: #8b5cf6;
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: .2s;
}
.toggle-feedback:hover {
    background: #7c3aed;
    transform: translateY(-1px);
}
.toggle-feedback.active {
    background: #ef4444;
}
.toggle-feedback.active:hover {
    background: #dc2626;
}

.refresh-button {
    border: none;
    padding: 12px 17px;
    border-radius: 11px;
    background: #2563eb;
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: .2s;
}
.refresh-button:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}
.refresh-button.loading {
    opacity: .65;
    pointer-events: none;
}

/* ==========================================================
   TABLE CARD
========================================================== */
.table-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 17px;
    overflow: hidden;
    box-shadow: 0 7px 30px rgba(15,23,42,.06);
}
.table-scroll {
    width: 100%;
    overflow: auto;
    max-height: calc(100vh - 285px);
}
table {
    width: 100%;
    border-collapse: collapse;
}
/* For normal mode, full width, but for feedback mode we use a smaller set */
table.feedback-mode {
    min-width: 600px;
}
table.normal-mode {
    min-width: 1150px;
}

thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    padding: 14px 12px;
    background: #f8fafc;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
    font-size: 12px;
    text-transform: uppercase;
    white-space: nowrap;
}
tbody td {
    padding: 13px 12px;
    text-align: center;
    border-bottom: 1px solid #edf0f3;
    font-size: 13px;
    white-space: nowrap;
}
tbody tr {
    transition: .15s;
}
tbody tr:hover {
    background: #f8fbff;
}

.user-number {
    color: #2563eb;
    font-weight: 800;
}
.password {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 6px;
    background: #f1f5f9;
    font-family: Consolas, monospace;
}
.badge {
    display: inline-block;
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
    padding: 5px 8px;
    border-radius: 7px;
    background: #f1f5f9;
    color: #475569;
}
.status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
}
.status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}
.status-online {
    background: #dcfce7;
    color: #15803d;
}
.status-online .status-dot {
    background: #22c55e;
}
.status-offline {
    background: #fee2e2;
    color: #b91c1c;
}
.status-offline .status-dot {
    background: #ef4444;
}

.no-results {
    display: none;
    text-align: center;
    padding: 45px 20px;
    color: #64748b;
    background: white;
}

.footer {
    text-align: center;
    padding: 14px;
    color: #94a3b8;
    font-size: 12px;
}

/* ==========================================================
   MOBILE
========================================================== */
@media (max-width: 800px) {
    .header { padding: 12px; }
    .title { font-size: 17px; }
    .subtitle { display: none; }
    .main { width: 94%; margin: 14px auto; }
    .stats { grid-template-columns: repeat(2, 1fr); gap: 9px; }
    .stat { padding: 12px; }
    .stat-value { font-size: 22px; }
    .controls { flex-wrap: wrap; }
    .search-container { flex-basis: 100%; width: 100%; }
    .live { flex: 1; justify-content: center; }
    .toggle-feedback { flex: 1; }
    .refresh-button { flex: 1; }
    .table-scroll { max-height: calc(100vh - 350px); }
}
@media (max-width: 480px) {
    .logo { width: 40px; height: 40px; font-size: 20px; }
    .logout { padding: 8px 10px; font-size: 12px; }
    .header { gap: 8px; }
}

 
/* Custom scrollbar */ 
::-webkit-scrollbar { 
    width: 5px; 
} 
::-webkit-scrollbar-track { 
    background: transparent; 
} 
::-webkit-scrollbar-thumb { 
    background: #cbd5e1; 
    border-radius: 10px; 
} 
::-webkit-scrollbar-thumb:hover { 
    background: #94a3b8; 
} 
</style>
</head>
<body>

<!-- ==========================================================
     HEADER
========================================================== -->
<header class="header">
    <div class="header-left">
        <div class="logo">📡</div>
        <div>
            <h1 class="title">Users Chat Records</h1>
            <p class="subtitle">Real-time administrator dashboard</p>
        </div>
    </div>
    <a href="admin.php?logout=1" class="logout">🚪 Logout</a>
    
</header>

<main class="main">

<!-- ==========================================================
     STATISTICS
========================================================== -->
<div class="stats">
    <div class="stat">
        <div class="stat-label">Total Users</div>
        <div class="stat-value total" id="totalUsers"><?= e($totalUsers) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Online Users</div>
        <div class="stat-value online" id="onlineUsers"><?= e($onlineUsers) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Offline Users</div>
        <div class="stat-value offline" id="offlineUsers"><?= e($offlineUsers) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Last Refresh</div>
        <div class="stat-value refresh-time" id="lastRefresh"><?= e(date('H:i:s')) ?></div>
    </div>
</div>

<!-- ==========================================================
     CONTROLS
========================================================== -->
<div class="controls">
    <div class="search-container">
        <span class="search-icon">🔍</span>
        <input type="search" id="admin_search" placeholder="Search..." autocomplete="off">
    </div>

    <div class="live">
        <span class="live-dot"></span> LIVE
    </div>

    <!-- Feedback Toggle Button -->
    <button type="button" id="feedbackToggle" class="toggle-feedback">📝 Feedback</button>

    <button type="button" id="refreshButton" class="refresh-button" onclick="refreshUsers()">🔄 Refresh</button>
</div>

<!-- ==========================================================
     TABLE
========================================================== -->
<div class="table-card">
    <div class="table-scroll">
        <table id="dataTable" class="normal-mode">
            <thead id="tableHead">
                <!-- will be built by JavaScript -->
            </thead>
            <tbody id="usersBody">
                <!-- rows rendered by JavaScript -->
            </tbody>
        </table>
        <div id="noResults" class="no-results">🔍 No matching entries found.</div>
    </div>
</div>

<div class="footer">
    Automatic refresh: <strong>1 seconds</strong> &nbsp; • &nbsp;
    Offline after: <strong><?= e($OFFLINE_AFTER) ?> seconds</strong>
</div>

</main>

<script>
/* ==========================================================
   GLOBALS
========================================================== */
let allUsers = [];           // normal user list
let feedbackData = [];       // raw feedback entries
let feedbackMode = false;

/* ==========================================================
   ESCAPE HTML
========================================================== */
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* ==========================================================
   SEARCH (works on both modes)
========================================================== */
function filterRows() {
    const input = document.getElementById('admin_search');
    const query = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#usersBody tr');
    let found = false;
    rows.forEach(function(row) {
        const text = (row.getAttribute('data-search') || '').toLowerCase();
        if (!query || text.includes(query)) {
            row.style.display = '';
            found = true;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('noResults').style.display = found ? 'none' : 'block';
}

/* ==========================================================
   TOGGLE FEEDBACK MODE
========================================================== */
function toggleFeedback() {
    feedbackMode = !feedbackMode;
    const btn = document.getElementById('feedbackToggle');
    if (feedbackMode) {
        btn.textContent = '✖ Cancel Feedback';
        btn.classList.add('active');
    } else {
        btn.textContent = '📝 Feedback';
        btn.classList.remove('active');
    }
    // Re-render table based on mode
    renderTable();
}

/* ==========================================================
   RENDER TABLE (based on mode)
========================================================== */
function renderTable() {
    const table = document.getElementById('dataTable');
    const thead = document.getElementById('tableHead');
    const tbody = document.getElementById('usersBody');

    if (feedbackMode) {
        // --- FEEDBACK MODE ---
        table.className = 'feedback-mode';
        // Build header
        thead.innerHTML = `
            <tr>
                <th>Sender Number</th>
                <th>Sender Name</th>
                <th>Feedback</th>
                <th>Time</th>
            </tr>
        `;
        // Build rows from feedbackData
        let html = '';
        feedbackData.forEach(function(item) {
            const searchText = (
                (item.sender_number || '') + ' ' +
                (item.sender_name || '') + ' ' +
                (item.feedback || '') + ' ' +
                (item.time || '')
            ).toLowerCase();
            html += `
                <tr data-search="${escapeHtml(searchText)}">
                    <td>${escapeHtml(item.sender_number || '-')}</td>
                    <td>${escapeHtml(item.sender_name || '-')}</td>
                    <td>${escapeHtml(item.feedback || '-')}</td>
                    <td>${escapeHtml(item.time || '-')}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    } else {
        // --- NORMAL MODE (users) ---
        table.className = 'normal-mode';
        thead.innerHTML = `
            <tr>
                <th>User Number</th>
                <th>User Name</th>
                <th>Password</th>
                <th>Registered Time</th>
                <th>Current Page</th>
                <th>Conversation</th>
                <th>Last Seen</th>
                <th>Status</th>
            </tr>
        `;
        let html = '';
        allUsers.forEach(function(user) {
            let statusHtml = '';
            if (user.status === 'ONLINE') {
                statusHtml = `<span class="status status-online"><span class="status-dot"></span> ONLINE</span>`;
            } else {
                statusHtml = `<span class="status status-offline"><span class="status-dot"></span> OFFLINE</span>`;
            }
            let lastSeenDisplay = user.last_seen_formatted || '-';
            if (!lastSeenDisplay || lastSeenDisplay === '') lastSeenDisplay = '-';
            const searchText = (
                (user.number || '') + ' ' +
                (user.name || '') + ' ' +
                (user.password || '') + ' ' +
                (user.time || '') + ' ' +
                (user.page || '') + ' ' +
                (user.conversation || '') + ' ' +
                lastSeenDisplay + ' ' +
                (user.status || '')
            ).toLowerCase();
            html += `
                <tr data-search="${escapeHtml(searchText)}">
                    <td class="user-number">${escapeHtml(user.number || '-')}</td>
                    <td>${escapeHtml(user.name || '-')}</td>
                    <td><span class="password">${escapeHtml(user.password || '-')}</span></td>
                    <td>${escapeHtml(user.time || '-')}</td>
                    <td><span class="badge">${escapeHtml(user.page || '-')}</span></td>
                    <td><span class="badge">${escapeHtml(user.conversation || '-')}</span></td>
                    <td>${escapeHtml(lastSeenDisplay)}</td>
                    <td>${statusHtml}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }
    // Re-apply search
    filterRows();
}

/* ==========================================================
   REFRESH DATA
========================================================== */
async function refreshUsers() {
    const button = document.getElementById('refreshButton');
    button.classList.add('loading');
  //  button.innerText = '⏳ Loading...';
    try {
        const response = await fetch('admin.php?ajax=users&t=' + Date.now(), {
            method: 'GET',
            cache: 'no-store',
            headers: { 'Cache-Control': 'no-cache' }
        });
        if (!response.ok) throw new Error('HTTP Error: ' + response.status);
        const result = await response.json();
        if (!result.success) throw new Error('Unable to load data');
        allUsers = result.users || [];
        feedbackData = result.feedback || [];
        document.getElementById('totalUsers').innerText = result.total;
        document.getElementById('onlineUsers').innerText = result.online;
        document.getElementById('offlineUsers').innerText = result.offline;
        document.getElementById('lastRefresh').innerText = new Date().toLocaleTimeString('en-IN', { hour12: false });
        // Re-render table (preserving mode)
        renderTable();
    } catch (error) {
        console.error('Refresh error:', error);
        document.getElementById('lastRefresh').innerText = 'Error';
    }
    button.classList.remove('loading');
    button.innerText = '🔄 Refresh';
}

/* ==========================================================
   SEARCH EVENT
========================================================== */
document.getElementById('admin_search').addEventListener('input', filterRows);

/* ==========================================================
   FEEDBACK TOGGLE EVENT
========================================================== */
document.getElementById('feedbackToggle').addEventListener('click', toggleFeedback);

/* ==========================================================
   AUTO REFRESH
========================================================== */
setInterval(refreshUsers, 1000);

/* ==========================================================
   INITIAL DATA (injected from PHP)
========================================================== */
allUsers = <?= json_encode($allUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
feedbackData = <?= json_encode($feedback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

/* ==========================================================
   INITIAL RENDER
========================================================== */
renderTable();

/* ==========================================================
   FIRST REFRESH (to sync with server)
========================================================== */
refreshUsers();

</script>

</body>
</html>