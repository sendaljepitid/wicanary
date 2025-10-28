<?php
session_start();

// Keamanan: Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Tentukan peran untuk logika tampilan
$user_role = $_SESSION['role'] ?? 'guest';
$is_admin = ($user_role === 'administrator'); // Admin Klien
$is_auditor = ($user_role === 'auditor');   // Auditor Klien
$is_operator = ($user_role === 'operator'); // Operator Klien
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Klien - WiCanary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { background-color: #f4f7fa; font-family: 'Inter', sans-serif; }
        .navbar { box-shadow: 0 2px 4px rgba(0,0,0,.05); }
        .card { border: 1px solid #e0e5eb; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        #sensorMap { height: 400px; border-radius: 0.5rem; }
        .log-container { max-height: 550px; overflow-y: auto; }
        .table-responsive { max-height: 400px; overflow-y: auto; overflow-x: auto; }
        .config-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        #open-chat-btn { position: fixed; bottom: 20px; right: 20px; z-index: 1050; }
        #ai-chat-container { position: fixed; bottom: 20px; right: 20px; width: 350px; height: 500px; z-index: 1049; flex-direction: column; display: none; }
        #chat-messages { flex-grow: 1; overflow-y: auto; padding: 1rem; }
        .chat-bubble { padding: 0.5rem 1rem; border-radius: 1rem; margin-bottom: 0.5rem; max-width: 80%; word-wrap: break-word; }
        .chat-bubble.user { background-color: #0d6efd; color: white; margin-left: auto; border-bottom-right-radius: 0; }
        .chat-bubble.ai { background-color: #e9ecef; color: #212529; margin-right: auto; border-bottom-left-radius: 0; }
        .marker-pin { width: 30px; height: 30px; border-radius: 50% 50% 50% 0; position: absolute; transform: rotate(-45deg); left: 50%; top: 50%; margin: -15px 0 0 -15px;}
        .marker-pin::after { content: ''; width: 14px; height: 14px; margin: 8px 0 0 8px; background: #fff; position: absolute; border-radius: 50%; }
        .custom-div-icon i { position: absolute; width: 22px; font-size: 12px; left: 0; right: 0; margin: 10px auto 0; text-align: center; color: white; }
        .badge.bg-success { background-color: #198754 !important; }
        .badge.bg-danger { background-color: #dc3545 !important; }
        .badge.bg-secondary { background-color: #6c757d !important; }
        .btn-period.active { background-color: #0d6efd; color: white; }
        .card-header-sensor { display: flex; justify-content: space-between; align-items: center; }
        /* Style untuk tombol delete user */
        .delete-user-btn { padding: 0.2rem 0.4rem; font-size: 0.8em; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-white sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-broadcast-tower me-2 text-primary"></i>
                WiCanary
                <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
            </a>

            <div class="d-flex align-items-center">
                <div class="text-end me-3 d-none d-md-block">
                    <small class="text-muted d-block" id="navbar-username">Loading...</small>
                    <strong class="d-block" id="navbar-companyname">Loading...</strong>
                </div>
                <div class="dropdown">
                    <a href="#" class="d-block link-dark text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle fa-2x text-primary"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end text-small shadow" aria-labelledby="dropdownUser">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                            <i class="fas fa-user-edit me-2"></i>Edit Profil
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>

        </div>
    </nav>
    <div class="container-fluid p-4">

        <?php if ($is_admin || $is_auditor): ?>
        <div class="card mb-4">
            <div class="card-header bg-white border-0 pt-3"><h5><i class="fas fa-file-pdf me-2"></i>Buat Laporan PDF</h5></div>
            <div class="card-body">
                <p class="text-muted">Unduh ringkasan aktivitas serangan.</p>
                <a href="generate_report.php?period=daily" class="btn btn-primary btn-sm" target="_blank">Laporan 24 Jam</a>
                <a href="generate_report.php?period=weekly" class="btn btn-secondary btn-sm" target="_blank">Laporan 7 Hari</a>
                <a href="generate_report.php?period=monthly" class="btn btn-info btn-sm" target="_blank">Laporan 30 Hari</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                         <h5 id="trendChartTitle"><i class="fas fa-chart-line me-2"></i>Tren Serangan (7 Hari Terakhir)</h5>
                         <div class="btn-group btn-group-sm" role="group" aria-label="Periode Grafik Tren">
                            <button type="button" class="btn btn-outline-secondary btn-period" data-period="24h">24 Jam</button>
                            <button type="button" class="btn btn-outline-secondary btn-period active" data-period="7d">7 Hari</button>
                            <button type="button" class="btn btn-outline-secondary btn-period" data-period="30d">30 Hari</button>
                            <button type="button" class="btn btn-outline-secondary btn-period" data-period="90d">90 Hari</button>
                            <button type="button" class="btn btn-outline-secondary btn-period" data-period="1y">1 Tahun</button>
                         </div>
                    </div>
                    <div class="card-body"><canvas id="trendChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white border-0 pt-3"><h5><i class="fas fa-map-marked-alt me-2"></i>Peta Geografis Sensor</h5></div>
                    <div class="card-body"><div id="sensorMap"></div></div>
                </div>
            </div>
        </div>

        <?php if ($is_admin): ?>
        <div class="card mb-4">
            <div class="card-header bg-white border-0 pt-3 card-header-sensor">
                <h5><i class="fas fa-sitemap me-2"></i>Manajemen Sensor</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSensorModal">
                    <i class="fas fa-plus me-1"></i> Tambah Sensor Baru
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>ID</th><th>Nama Sensor</th><th>MAC Address</th><th>Koordinat</th><th>Status</th><th>Aksi</th></tr>
                        </thead>
                        <tbody id="sensor-list-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white border-0 pt-3 card-header-sensor">
                <h5><i class="fas fa-users-cog me-2"></i>Manajemen Pengguna Internal</h5>
                <button id="add-user-button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus me-1"></i> Tambah Pengguna Baru
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Username</th><th>Peran (Role)</th><th>Dibuat Tanggal</th><th>Aksi</th></tr>
                        </thead>
                        <tbody id="user-list-body"></tbody>
                    </table>
                     <div class="p-3 border-top">
                        <small class="text-muted" id="user-count-info">Memuat info kuota...</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-white border-0 pt-3"><h5><i class="fas fa-history me-2"></i>Log Peringatan Real-time (200 Terbaru)</h5></div>
            <div class="card-body p-0 log-container">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Waktu</th><th>Jenis Serangan</th><th>Target SSID</th><th>MAC Terkait</th><th>RSSI</th><th>Sensor</th></tr>
                        </thead>
                        <tbody id="attack-log-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <footer class="text-center py-4"><p class="text-muted small">&copy; 2025 Budi Wibowo - ITBU</p></footer>


    <?php if ($is_admin): ?>
    <div class="modal fade" id="addSensorModal" tabindex="-1" aria-labelledby="addSensorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="addSensorModalLabel">Tambah Sensor Baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <form id="addSensorForm">
                    <div class="modal-body">
                        <div id="addSensorError" class="alert alert-danger" style="display: none;" role="alert"></div>
                        <div class="mb-3"><label for="addSensorNameInput" class="form-label">Nama Sensor</label><input type="text" class="form-control" id="addSensorNameInput" name="name" required></div>
                        <div class="mb-3"><label for="addSensorMacInput" class="form-label">MAC Address</label><input type="text" class="form-control" id="addSensorMacInput" name="mac_address" placeholder="XX:XX:XX:XX:XX:XX" required></div>
                        <div class="mb-3"><label for="addSensorLatInput" class="form-label">Latitude</label><input type="text" class="form-control" id="addSensorLatInput" name="latitude" required></div>
                        <div class="mb-3"><label for="addSensorLonInput" class="form-label">Longitude</label><input type="text" class="form-control" id="addSensorLonInput" name="longitude" required></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Sensor</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editSensorModal" tabindex="-1" aria-labelledby="editSensorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="editSensorModalLabel">Edit Detail & Konfigurasi Sensor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <form id="editSensorForm">
                    <div class="modal-body">
                        <input type="hidden" id="sensorIdInput" name="id"><h6>Informasi Dasar</h6>
                        <div class="row mb-3"><div class="col-md-6"><label for="sensorNameInput" class="form-label">Nama Sensor</label><input type="text" class="form-control" id="sensorNameInput" name="name" required></div><div class="col-md-6"><label for="sensorMacInput" class="form-label">MAC Address</label><input type="text" class="form-control" id="sensorMacInput" name="mac_address" readonly disabled></div></div>
                        <div class="row mb-4"><div class="col-md-6"><label for="sensorLatInput" class="form-label">Latitude</label><input type="text" class="form-control" id="sensorLatInput" name="latitude" required></div><div class="col-md-6"><label for="sensorLonInput" class="form-label">Longitude</label><input type="text" class="form-control" id="sensorLonInput" name="longitude" required></div></div><hr>
                        <h6>Konfigurasi Threshold Deteksi (per detik)</h6><p class="text-muted small">Kosongkan untuk default server.</p>
                        <div class="config-grid">
                            <div><label for="configDeauth" class="form-label">Deauth Score</label><input type="number" class="form-control" id="configDeauth" name="config_max_deauth_score" min="1"></div>
                            <div><label for="configBeacon" class="form-label">Beacon Count</label><input type="number" class="form-control" id="configBeacon" name="config_max_beacon_count" min="1"></div>
                            <div><label for="configProbe" class="form-label">Probe Req Count</label><input type="number" class="form-control" id="configProbe" name="config_max_probe_req_count" min="1"></div>
                            <div><label for="configAuth" class="form-label">Auth Req Count</label><input type="number" class="form-control" id="configAuth" name="config_max_auth_req_count" min="1"></div>
                            <div><label for="configAssoc" class="form-label">Assoc Req Count</label><input type="number" class="form-control" id="configAssoc" name="config_max_assoc_req_count" min="1"></div>
                            <div><label for="configRts" class="form-label">RTS Count</label><input type="number" class="form-control" id="configRts" name="config_max_rts_count" min="1"></div>
                            <div><label for="configCts" class="form-label">CTS Count</label><input type="number" class="form-control" id="configCts" name="config_max_cts_count" min="1"></div>
                            <div><label for="configCheck" class="form-label">Config Interval (ms)</label><input type="number" class="form-control" id="configCheck" name="config_check_interval_ms" min="60000"></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="addUserModalLabel">Tambah Pengguna Baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <form id="addUserForm">
                    <div class="modal-body">
                        <div id="addUserError" class="alert alert-danger" style="display: none;" role="alert"></div>
                        <div class="mb-3"><label for="addUsernameInput" class="form-label">Username</label><input type="text" class="form-control" id="addUsernameInput" name="username" required></div>
                        <div class="mb-3"><label for="addPasswordInput" class="form-label">Password</label><input type="password" class="form-control" id="addPasswordInput" name="password" required autocomplete="new-password"><small class="text-muted">Minimal 6 karakter.</small></div>
                        <div class="mb-3"><label for="addRoleInput" class="form-label">Peran (Role)</label><select class="form-select" id="addRoleInput" name="role" required><option value="">-- Pilih Peran --</option><option value="auditor">Auditor</option><option value="operator">Operator</option></select></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Pengguna</button></div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="profileModalLabel">Edit Profil Anda</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <form id="profileForm">
                    <div class="modal-body">
                        <div id="profileError" class="alert alert-danger" style="display: none;"></div><div id="profileSuccess" class="alert alert-success" style="display: none;"></div>
                        <div class="mb-3"><label for="profileUsername" class="form-label">Username</label><input type="text" class="form-control" id="profileUsername" name="username" readonly disabled><small class="text-muted">Username tidak dapat diubah.</small></div><hr>
                        <p class="text-muted">Ganti Password (Isi jika ingin ganti)</p>
                        <div class="mb-3"><label for="profileNewPassword" class="form-label">Password Baru</label><input type="password" class="form-control" id="profileNewPassword" name="new_password" autocomplete="new-password"></div>
                        <div class="mb-3"><label for="profileConfirmPassword" class="form-label">Konfirmasi Password Baru</label><input type="password" class="form-control" id="profileConfirmPassword" name="confirm_password" autocomplete="new-password"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
                </form>
            </div>
        </div>
    </div>


     <?php if ($is_admin): ?>
    <div id="ai-chat-container" class="card shadow-lg"><div class="card-header bg-primary text-white d-flex justify-content-between align-items-center"><span><i class="fas fa-robot me-2"></i>WiCanary AI Assistant</span><button id="close-chat-btn" type="button" class="btn-close btn-close-white"></button></div><div class="card-body" id="chat-messages"></div><div class="card-footer"><div class="input-group"><input type="text" id="chat-input" class="form-control" placeholder="Tanya sesuatu..."><button class="btn btn-primary" type="button" id="send-chat-btn"><i class="fas fa-paper-plane"></i></button></div></div></div>
    <button id="open-chat-btn" class="btn btn-primary btn-lg rounded-circle shadow-lg"><i class="fas fa-robot"></i></button>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

            let currentTrendPeriod = '7d';
            const sensorTableBody = document.getElementById('sensor-list-body');
            let map;
            let sensorMarkers = {};
            let trendChart;

            // Inisialisasi Peta
            try {
                map = L.map('sensorMap').setView([-2.5489, 118.0149], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OSM' }).addTo(map);
            } catch (e) { console.error("Peta Gagal:", e); document.getElementById('sensorMap').innerHTML = '<div class="alert alert-danger">Gagal memuat peta.</div>'; }

            // Inisialisasi Grafik
            try {
                const ctx = document.getElementById('trendChart').getContext('2d');
                trendChart = new Chart(ctx, { type: 'line', data: { labels: [], datasets: [{ label: 'Jumlah Serangan', data: [], borderColor: '#0d6efd', borderWidth: 2, tension: 0.1, fill: false }] }, options: { responsive: true, maintainAspectRatio: false } });
            } catch (e) { console.error("Grafik Gagal:", e); document.getElementById('trendChart').innerHTML = '<div class="alert alert-danger">Gagal memuat grafik.</div>'; }

            async function fetchAdminData(period = '7d') {
                try {
                    const response = await fetch(`get_admin_data.php?trend_period=${period}`);
                    if (!response.ok) throw new Error(`HTTP error! ${response.status}`);
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) { const text = await response.text(); throw new Error(`Bukan JSON: ${text}`); }
                    const data = await response.json();
                    if (data.error) throw new Error(data.error);
                    if (!data) return;

                    // Isi Info Akun di Navbar
                    if (data.account_info) {
                        document.getElementById('navbar-username').textContent = escapeHTML(data.account_info.username);
                        document.getElementById('navbar-companyname').textContent = escapeHTML(data.account_info.company_name);
                        const profileUsernameEl = document.getElementById('profileUsername');
                        if (profileUsernameEl) profileUsernameEl.value = data.account_info.username;
                    }

                    // Perbarui Tabel Log
                    const logBody = document.getElementById('attack-log-body');
                    if (logBody) {
                        logBody.innerHTML = '';
                        if (!data.logs || data.logs.length === 0) logBody.innerHTML = '<tr><td colspan="6" class="text-center p-4 text-muted">Belum ada aktivitas.</td></tr>';
                        else data.logs.forEach(log => {
                            const sensorDisplay = log.sensor_name ? `${escapeHTML(log.sensor_name)} (${escapeHTML(log.sensor_mac)})` : escapeHTML(log.sensor_mac);
                            logBody.innerHTML += `<tr><td>${escapeHTML(log.timestamp)}</td><td><strong>${escapeHTML((log.attack_type || '').replace(/_/g, ' '))}</strong></td><td>${escapeHTML(log.ssid || 'N/A')}</td><td><code>${escapeHTML(log.mac || 'N/A')}</code></td><td>${escapeHTML(log.rssi || '-')}</td><td><code>${sensorDisplay || 'N/A'}</code></td></tr>`;
                        });
                    }

                    // Perbarui Grafik Tren & Judulnya
                    if (trendChart && data.trend_data && Array.isArray(data.trend_data)) {
                        trendChart.data.labels = data.trend_data.map(d => d.attack_period);
                        trendChart.data.datasets[0].data = data.trend_data.map(d => d.count);
                        trendChart.update();
                        const chartTitleEl = document.getElementById('trendChartTitle');
                        if(chartTitleEl && data.trend_title) chartTitleEl.innerHTML = `<i class="fas fa-chart-line me-2"></i>${escapeHTML(data.trend_title)}`;
                    }

                    // Perbarui Peta & Tabel Sensor
                    if (isAdmin && sensorTableBody) sensorTableBody.innerHTML = '';
                    if (map) { Object.values(sensorMarkers).forEach(m => map.removeLayer(m)); sensorMarkers = {}; }

                    if (data.sensors && Array.isArray(data.sensors) && data.sensors.length > 0) {
                        data.sensors.forEach(sensor => {
                            if (map) {
                                const iconColor = sensor.status === 'Online' ? '#198754' : (sensor.status === 'Offline' ? '#dc3545' : '#6c757d');
                                const icon = L.divIcon({ className: 'custom-div-icon', html: `<div style='background-color:${iconColor};' class='marker-pin'></div><i class='fa fa-wifi'></i>`, iconSize: [30, 42], iconAnchor: [15, 42] });
                                if (sensor.latitude && sensor.longitude && !isNaN(parseFloat(sensor.latitude)) && !isNaN(parseFloat(sensor.longitude))) {
                                    const latLng = [parseFloat(sensor.latitude), parseFloat(sensor.longitude)];
                                    sensorMarkers[sensor.mac_address] = L.marker(latLng, { icon: icon }).addTo(map).bindPopup(`<b>${escapeHTML(sensor.name)}</b><br>Status: ${escapeHTML(sensor.status)}<br>MAC: ${escapeHTML(sensor.mac_address)}`);
                                }
                            }
                            if (isAdmin && sensorTableBody) {
                                let statusBadge; switch (sensor.status) { case 'Online': statusBadge = '<span class="badge bg-success">Online</span>'; break; case 'Offline': statusBadge = '<span class="badge bg-danger">Offline</span>'; break; default: statusBadge = '<span class="badge bg-secondary">Never Seen</span>'; break; }
                                const editBtn = `<button class="btn btn-sm btn-outline-primary edit-sensor-btn" data-bs-toggle="modal" data-bs-target="#editSensorModal" data-id="${escapeHTML(sensor.id)}" data-name="${escapeHTML(sensor.name)}" data-mac="${escapeHTML(sensor.mac_address)}" data-lat="${escapeHTML(sensor.latitude ?? '')}" data-lon="${escapeHTML(sensor.longitude ?? '')}" data-config-deauth="${escapeHTML(sensor.config_max_deauth_score ?? '')}" data-config-beacon="${escapeHTML(sensor.config_max_beacon_count ?? '')}" data-config-probe="${escapeHTML(sensor.config_max_probe_req_count ?? '')}" data-config-auth="${escapeHTML(sensor.config_max_auth_req_count ?? '')}" data-config-assoc="${escapeHTML(sensor.config_max_assoc_req_count ?? '')}" data-config-rts="${escapeHTML(sensor.config_max_rts_count ?? '')}" data-config-cts="${escapeHTML(sensor.config_max_cts_count ?? '')}" data-config-check="${escapeHTML(sensor.config_check_interval_ms ?? '')}"><i class="fas fa-edit"></i> Edit</button>`;
                                sensorTableBody.innerHTML += `<tr><td>${escapeHTML(sensor.id)}</td><td>${escapeHTML(sensor.name)}</td><td><code>${escapeHTML(sensor.mac_address)}</code></td><td>${escapeHTML(sensor.latitude || '-')} , ${escapeHTML(sensor.longitude || '-')}</td><td>${statusBadge}</td><td>${editBtn}</td></tr>`;
                             }
                        });
                    } else if (isAdmin && sensorTableBody) {
                        sensorTableBody.innerHTML = '<tr><td colspan="6" class="text-center">Belum ada sensor terdaftar.</td></tr>';
                    }

                    // Perbarui Tabel Pengguna (Hanya Admin)
                    if (isAdmin) {
                        const userTableBody = document.getElementById('user-list-body');
                        const userCountInfo = document.getElementById('user-count-info');
                        const addUserButton = document.getElementById('add-user-button');

                        if (userTableBody && data.users && Array.isArray(data.users)) {
                            userTableBody.innerHTML = '';
                            const currentUserCount = data.users.length;
                            let loggedInUsername = data.account_info ? data.account_info.username : ''; // Ambil username yang login

                            data.users.forEach(user => {
                                let deleteButton = '';
                                if (user.username !== loggedInUsername) { // Jangan bisa hapus diri sendiri
                                     deleteButton = ` <button class="btn btn-sm btn-outline-danger delete-user-btn" data-user-id="${escapeHTML(user.id)}" data-username="${escapeHTML(user.username)}"><i class="fas fa-trash-alt"></i></button>`;
                                }
                                userTableBody.innerHTML += `<tr><td>${escapeHTML(user.username)}</td><td>${escapeHTML(user.role)}</td><td>${escapeHTML(user.created_at)}</td><td>${deleteButton}</td></tr>`;
                            });

                            if (userCountInfo) userCountInfo.textContent = `Pengguna saat ini: ${currentUserCount} dari 5 maksimal.`;
                            if (addUserButton) { addUserButton.disabled = (currentUserCount >= 5); addUserButton.title = (currentUserCount >= 5) ? "Kuota pengguna penuh." : ""; }
                        } else if (userTableBody) {
                             userTableBody.innerHTML = '<tr><td colspan="4" class="text-center">Gagal memuat pengguna.</td></tr>';
                             if (userCountInfo) userCountInfo.textContent = 'Gagal memuat kuota.';
                             if (addUserButton) addUserButton.disabled = true;
                        }
                    } // end if(isAdmin) user table update

                } catch (error) { // Blok catch untuk fetchAdminData
                    console.error("Gagal fetch data admin:", error);
                    const logBody = document.getElementById('attack-log-body'); if(logBody) logBody.innerHTML = `<tr><td colspan="6" class="text-center p-4 text-danger">Gagal memuat log: ${escapeHTML(error.message)}</td></tr>`;
                    if(isAdmin) {
                         const sensorBody = document.getElementById('sensor-list-body'); if(sensorBody) sensorBody.innerHTML = `<tr><td colspan="6" class="text-center p-4 text-danger">Gagal memuat sensor: ${escapeHTML(error.message)}</td></tr>`;
                         const userBody = document.getElementById('user-list-body'); if(userBody) userBody.innerHTML = `<tr><td colspan="4" class="text-center p-4 text-danger">Gagal memuat pengguna: ${escapeHTML(error.message)}</td></tr>`;
                         const userBtn = document.getElementById('add-user-button'); if(userBtn) userBtn.disabled = true;
                         const userInfo = document.getElementById('user-count-info'); if(userInfo) userInfo.textContent='Error kuota.';
                    }
                } // Akhir blok catch fetchAdminData
            } // Akhir fungsi fetchAdminData

            fetchAdminData(currentTrendPeriod);
            setInterval(() => fetchAdminData(currentTrendPeriod), 10000);

            // Listener Tombol Periode Grafik
            const periodButtons = document.querySelectorAll('.btn-period');
            periodButtons.forEach(button => {
                button.addEventListener('click', () => {
                    periodButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    currentTrendPeriod = button.getAttribute('data-period');
                    fetchAdminData(currentTrendPeriod);
                });
            });

            // --- Event Listener untuk Modal Admin Klien ---
            if (isAdmin) {
                // Inisialisasi Modal
                const editSensorModalEl = document.getElementById('editSensorModal');
                const editSensorModal = editSensorModalEl ? new bootstrap.Modal(editSensorModalEl) : null;
                const editSensorForm = document.getElementById('editSensorForm');
                const addSensorModalEl = document.getElementById('addSensorModal');
                const addSensorModal = addSensorModalEl ? new bootstrap.Modal(addSensorModalEl) : null;
                const addSensorForm = document.getElementById('addSensorForm');
                const addSensorErrorDiv = document.getElementById('addSensorError');
                const addUserModalEl = document.getElementById('addUserModal');
                const addUserModal = addUserModalEl ? new bootstrap.Modal(addUserModalEl) : null;
                const addUserForm = document.getElementById('addUserForm');
                const addUserErrorDiv = document.getElementById('addUserError');
                const userListBody = document.getElementById('user-list-body');


                // Listener Isi Modal EDIT Sensor
                if (editSensorModalEl) {
                    editSensorModalEl.addEventListener('show.bs.modal', e => {
                        try { const btn = e.relatedTarget; const get = (n) => btn.getAttribute(`data-${n}`) || '';
                        editSensorModalEl.querySelector('#sensorIdInput').value = get('id'); editSensorModalEl.querySelector('#sensorNameInput').value = get('name'); editSensorModalEl.querySelector('#sensorMacInput').value = get('mac');
                        editSensorModalEl.querySelector('#sensorLatInput').value = get('lat'); editSensorModalEl.querySelector('#sensorLonInput').value = get('lon');
                        editSensorModalEl.querySelector('#configDeauth').value = get('config-deauth'); editSensorModalEl.querySelector('#configBeacon').value = get('config-beacon'); editSensorModalEl.querySelector('#configProbe').value = get('config-probe');
                        editSensorModalEl.querySelector('#configAuth').value = get('config-auth'); editSensorModalEl.querySelector('#configAssoc').value = get('config-assoc');
                        editSensorModalEl.querySelector('#configRts').value = get('config-rts'); editSensorModalEl.querySelector('#configCts').value = get('config-cts');
                        editSensorModalEl.querySelector('#configCheck').value = get('config-check');
                        } catch (err) { console.error("Error isi modal edit sensor:", err); alert("Gagal memuat detail sensor."); }
                    });
                }

                // Listener Submit Form EDIT Sensor
                if (editSensorForm) {
                    editSensorForm.addEventListener('submit', async e => {
                        e.preventDefault(); const fd = new FormData(editSensorForm); fd.append('mac_address', document.getElementById('sensorMacInput').value);
                        try {
                            const res = await fetch('update_sensor.php', { method: 'POST', body: fd });
                            if (!res.ok) throw new Error(`HTTP error! ${res.status}`);
                            const result = await res.json();
                            if (result.status === 'success' || result.status === 'info') { alert(result.message || 'Diproses!'); if (editSensorModal) editSensorModal.hide(); fetchAdminData(currentTrendPeriod); }
                            else alert('Gagal: ' + (result.message || 'Error'));
                        } catch (err) { console.error('Error update sensor:', err); alert('Kesalahan koneksi saat update sensor.'); }
                    });
                }

                // Listener Submit Form TAMBAH Sensor
                if (addSensorForm) {
                    addSensorForm.addEventListener('submit', async e => {
                        e.preventDefault(); if (addSensorErrorDiv) addSensorErrorDiv.style.display = 'none'; const fd = new FormData(addSensorForm);
                        try {
                            const res = await fetch('create_sensor.php', { method: 'POST', body: fd });
                            const result = await res.json();
                            if (res.ok && result.status === 'success') { alert('Sensor ditambahkan!'); if (addSensorModal) addSensorModal.hide(); addSensorForm.reset(); fetchAdminData(currentTrendPeriod); }
                            else {
                                const msg = result.message || 'Error server.';
                                if (addSensorErrorDiv) { addSensorErrorDiv.textContent = msg; addSensorErrorDiv.style.display = 'block'; }
                                else alert('Gagal tambah sensor: ' + msg);
                            }
                        } catch (err) {
                             console.error('Error tambah sensor:', err);
                             if (addSensorErrorDiv) { addSensorErrorDiv.textContent = 'Kesalahan koneksi saat tambah sensor.'; addSensorErrorDiv.style.display = 'block'; }
                             else alert('Kesalahan koneksi saat tambah sensor.');
                        }
                    });
                }

                // Listener Submit Form TAMBAH Pengguna
                if (addUserForm) {
                    addUserForm.addEventListener('submit', async event => {
                        event.preventDefault(); if (addUserErrorDiv) addUserErrorDiv.style.display = 'none'; const fd = new FormData(addUserForm);
                        try {
                            const response = await fetch('create_user.php', { method: 'POST', body: fd });
                            const result = await response.json();
                            if (response.ok && result.status === 'success') { alert('Pengguna baru ditambahkan!'); if (addUserModal) addUserModal.hide(); addUserForm.reset(); fetchAdminData(currentTrendPeriod); }
                            else {
                                const msg = result.message || 'Error server.';
                                if (addUserErrorDiv) { addUserErrorDiv.textContent = msg; addUserErrorDiv.style.display = 'block'; }
                                else alert('Gagal tambah pengguna: ' + msg);
                            }
                        } catch (err) {
                             console.error('Error tambah pengguna:', err);
                             if (addUserErrorDiv) { addUserErrorDiv.textContent = 'Kesalahan koneksi saat tambah pengguna.'; addUserErrorDiv.style.display = 'block'; }
                             else alert('Kesalahan koneksi saat tambah pengguna.');
                        }
                    });
                }

                 // Listener Tombol HAPUS Pengguna
                 if (userListBody) {
                    userListBody.addEventListener('click', async event => {
                        // Periksa apakah tombol hapus atau ikon di dalamnya yang diklik
                        const button = event.target.closest('.delete-user-btn');
                        if (button) {
                            const userId = button.dataset.userId;
                            const username = button.dataset.username;

                            if (confirm(`Apakah Anda yakin ingin menghapus pengguna "${username}"?`)) {
                                try {
                                    const formData = new FormData();
                                    formData.append('user_id', userId);
                                    const response = await fetch('delete_user.php', { method: 'POST', body: formData });
                                    const result = await response.json();
                                    if (response.ok && result.status === 'success') { alert('Pengguna dihapus.'); fetchAdminData(currentTrendPeriod); }
                                    else alert('Gagal hapus: ' + (result.message || 'Error'));
                                } catch (error) { console.error('Error hapus pengguna:', error); alert('Kesalahan koneksi saat hapus.'); }
                            }
                        }
                    });
                 }

            } // end if(isAdmin)

            // --- Event Listener untuk Modal Profil ---
            const profileForm = document.getElementById('profileForm');
            const profileErrorDiv = document.getElementById('profileError');
            const profileSuccessDiv = document.getElementById('profileSuccess');
            const profileNewPassword = document.getElementById('profileNewPassword');
            const profileConfirmPassword = document.getElementById('profileConfirmPassword');

            if (profileForm) {
                profileForm.addEventListener('submit', async event => {
                    event.preventDefault(); profileErrorDiv.style.display = 'none'; profileSuccessDiv.style.display = 'none';
                    const newPasswordVal = profileNewPassword.value; const confirmPasswordVal = profileConfirmPassword.value;

                    if (newPasswordVal) {
                        if (newPasswordVal !== confirmPasswordVal) { profileErrorDiv.textContent = 'Password baru dan konfirmasi tidak cocok.'; profileErrorDiv.style.display = 'block'; return; }
                        const fd = new FormData(); fd.append('new_password', newPasswordVal);
                        try {
                            const response = await fetch('update_profile.php', { method: 'POST', body: fd });
                            const result = await response.json();
                            if (response.ok && result.status === 'success') {
                                profileSuccessDiv.textContent = 'Password berhasil diperbarui!'; profileSuccessDiv.style.display = 'block';
                                profileNewPassword.value = ''; profileConfirmPassword.value = '';
                            } else { profileErrorDiv.textContent = result.message || 'Gagal update profil.'; profileErrorDiv.style.display = 'block'; }
                        } catch (error) { profileErrorDiv.textContent = 'Kesalahan koneksi update profil.'; profileErrorDiv.style.display = 'block'; }
                    } else { profileSuccessDiv.textContent = 'Tidak ada perubahan disimpan.'; profileSuccessDiv.style.display = 'block'; }
                });
            }

            // --- Event Listener untuk AI Chat ---
            const openChatBtn = document.getElementById('open-chat-btn');
            const closeChatBtn = document.getElementById('close-chat-btn');
            const chatContainer = document.getElementById('ai-chat-container');
            if (openChatBtn && chatContainer) openChatBtn.addEventListener('click', () => { chatContainer.style.display = 'flex'; openChatBtn.style.display = 'none'; });
            if (closeChatBtn && chatContainer && openChatBtn) closeChatBtn.addEventListener('click', () => { chatContainer.style.display = 'none'; openChatBtn.style.display = 'block'; });

        }); // Akhir DOMContentLoaded
    </script>

    <?php if ($is_admin): ?>
    <script src="admin_chat.js"></script>
    <?php endif; ?>

</body>
</html>