<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WiCanary - Sistem Deteksi Intrusi Wi-Fi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- [BARU] Library Peta Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        :root {
            --dark-bg: #0a192f;
            --light-bg: #112240;
            --accent-color: #0ea5e9; /* Biru elektrik */
            --text-primary: #ccd6f6;
            --text-secondary: #8892b0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-primary);
        }
        .navbar {
            background-color: rgba(10, 25, 47, 0.85);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px -10px rgba(2,12,27,0.7);
        }
        .navbar-brand {
            font-family: 'Roboto Mono', monospace;
            color: var(--text-primary) !important;
        }
        .btn-outline-primary { /* Ganti style tombol login */
            border-color: var(--accent-color);
            color: var(--accent-color);
        }
        .btn-outline-primary:hover {
            background-color: rgba(14, 165, 233, 0.1);
            border-color: var(--accent-color);
            color: var(--accent-color);
        }
        .hero {
            padding: 6rem 0;
            text-align: center;
        }
        .hero h1 {
            color: var(--accent-color);
            font-family: 'Roboto Mono', monospace;
        }
        .section-title {
            color: var(--text-primary);
            font-family: 'Roboto Mono', monospace;
            margin-bottom: 2.5rem;
            margin-top: 3rem; /* Beri jarak antar section */
        }
        .feature-card, .summary-card, .stat-card {
            background-color: var(--light-bg);
            border: 1px solid #1d2d50;
            border-radius: 0.5rem;
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 10px 30px -15px rgba(2,12,27,0.7);
        }
        .feature-card:hover, .summary-card:hover, .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 15px rgba(14, 165, 233, 0.3);
        }
        .feature-icon { font-size: 3rem; color: var(--accent-color); margin-bottom: 1.5rem; }
        .feature-card h5, .card-header h5, .stat-card h3 { color: var(--text-primary); }
        .feature-card p, .stat-card p { color: var(--text-secondary); }

        .summary-list .list-group-item { background-color: transparent; border-color: #1d2d50; color: var(--text-secondary); }
        .summary-list code { color: var(--accent-color); }
        .summary-list .badge { font-family: 'Roboto Mono', monospace; }
        .text-accent { color: var(--accent-color) !important; }

        /* Styling elemen baru */
        .stat-card { text-align: center; padding: 2rem 1rem; }
        #sensorMapPublic { height: 400px; border-radius: 0.5rem; background-color: #081428; /* Latar belakang gelap untuk peta */ }
        .leaflet-tile-pane { filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%); } /* Dark mode tile peta */
        .leaflet-popup-content-wrapper, .leaflet-popup-tip { background-color: var(--dark-bg); color: var(--text-primary); border: 1px solid #1d2d50; box-shadow: 0 1px 5px rgba(0,0,0,.4); }
        .leaflet-container a.leaflet-popup-close-button { color: var(--text-secondary); }

        #latest-logs-list .list-group-item { padding: 0.75rem 1rem; }

        footer { background-color: var(--light-bg); border-top: 1px solid #1d2d50; }
        /* Escape HTML function */
         .hidden { display: none; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="#"><i class="fas fa-broadcast-tower me-2"></i>WiCanary</a>
            <a href="login.php" class="btn btn-outline-primary">Admin Login</a>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">[ WiCanary ]</h1>
            <p class="lead text-secondary col-lg-8 mx-auto">Sistem Deteksi Intrusi Terdistribusi Berbasis IoT untuk Pemantauan Jaringan Nirkabel secara Real-Time.</p>
        </div>
    </header>

    <main class="container py-5">

        <!-- [BARU] Bagian Statistik Utama -->
        <section id="statistics" class="mb-5">
             <h2 class="text-center section-title">Statistik Sistem</h2>
            <div class="row g-4 justify-content-center">
                <div class="col-md-5">
                    <div class="stat-card">
                        <h3 class="display-4 fw-bold text-accent" id="total-attacks-stat">...</h3>
                        <p class="text-secondary mb-0 fs-5"><i class="fas fa-exclamation-triangle me-2"></i>Total Serangan Terdeteksi</p>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="stat-card">
                        <h3 class="display-4 fw-bold text-accent" id="total-sensors-stat">...</h3>
                        <p class="text-secondary mb-0 fs-5"><i class="fas fa-satellite-dish me-2"></i>Sensor Terdistribusi Aktif</p>
                    </div>
                </div>
            </div>
        </section>

         <!-- [BARU] Peta Sensor Publik -->
        <section id="sensor-map-public" class="my-5 py-5">
            <h2 class="text-center section-title">Jangkauan Pemantauan</h2>
             <div class="card summary-card p-3 shadow-lg">
                <div id="sensorMapPublic"></div>
            </div>
        </section>

        <!-- Ringkasan Aktivitas Serangan -->
        <section id="summary" class="my-5 py-5">
             <div class="container">
                <h2 class="text-center section-title">Ringkasan Aktivitas Serangan</h2>
                <div class="row g-4">
                    <div class="col-lg-4"><div class="card summary-card"><div class="card-header bg-transparent border-0 pt-3"><h5 class="mb-0"><i class="fas fa-chart-pie me-2 text-accent"></i>Tipe Serangan</h5></div><div class="card-body p-0"><ul class="list-group list-group-flush summary-list" id="attack-types-summary"><li class="list-group-item text-center p-4"><div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div></li></ul></div></div></div>
                    <div class="col-lg-4"><div class="card summary-card"><div class="card-header bg-transparent border-0 pt-3"><h5 class="mb-0"><i class="fas fa-crosshairs me-2 text-accent"></i>Top Pelaku</h5></div><div class="card-body p-0"><ul class="list-group list-group-flush summary-list" id="top-attackers-summary"><li class="list-group-item text-center p-4"><div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div></li></ul></div></div></div>
                    <div class="col-lg-4"><div class="card summary-card"><div class="card-header bg-transparent border-0 pt-3"><h5 class="mb-0"><i class="fas fa-bullseye me-2 text-accent"></i>Top Target SSID</h5></div><div class="card-body p-0"><ul class="list-group list-group-flush summary-list" id="top-targets-summary"><li class="list-group-item text-center p-4"><div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div></li></ul></div></div></div>
                </div>
            </div>
        </section>

        <!-- [BARU] Log Aktivitas Terbaru -->
        <section id="latest-activity" class="my-5 py-5">
            <h2 class="text-center section-title">Aktivitas Terbaru</h2>
            <div class="col-lg-8 mx-auto">
                <div class="card summary-card">
                     <div class="card-header bg-transparent border-0 pt-3"><h5 class="mb-0"><i class="fas fa-history me-2 text-accent"></i>5 Peringatan Terakhir</h5></div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush summary-list" id="latest-logs-list">
                           <li class="list-group-item text-center p-4"><div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Fitur Utama -->
        <section id="about" class="my-5 py-5">
             <h2 class="text-center section-title">Fitur Utama</h2>
            <div class="row g-4">
                <div class="col-md-4"><div class="card feature-card"><div class="card-body d-flex flex-column align-items-center text-center"><i class="fas fa-wave-square feature-icon"></i><h5 class="fw-semibold mb-2">Deteksi Real-Time</h5><p class="small">Memantau lalu lintas jaringan secara terus-menerus untuk mendeteksi berbagai jenis serangan.</p></div></div></div>
                <div class="col-md-4"><div class="card feature-card"><div class="card-body d-flex flex-column align-items-center text-center"><i class="fas fa-microchip feature-icon"></i><h5 class="fw-semibold mb-2">Sensor Berbasis IoT</h5><p class="small">Memanfaatkan perangkat ESP32 berbiaya rendah sebagai sensor terdistribusi untuk jangkauan luas.</p></div></div></div>
                <div class="col-md-4"><div class="card feature-card"><div class="card-body d-flex flex-column align-items-center text-center"><i class="fas fa-tachometer-alt feature-icon"></i><h5 class="fw-semibold mb-2">Dasbor Terpusat</h5><p class="small">Menyajikan semua data serangan dalam dasbor web informatif untuk dianalisis.</p></div></div></div>
            </div>
        </section>
    </main>

    <footer class="text-center py-4 mt-5">
        <p class="text-secondary small mb-0">&copy; 2025 Budi Wibowo - ITBU</p>
    </footer>

    <!-- Pustaka Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi escape HTML sederhana
        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi Peta Publik Sekali Saja
            let publicMap = null;
            const mapElement = document.getElementById('sensorMapPublic');
            if (mapElement) {
                publicMap = L.map(mapElement).setView([-2.548926, 118.0148634], 5); // Center Indonesia
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(publicMap);
            }
            let sensorMarkersPublic = {}; // Objek untuk menyimpan marker peta publik

            const noDataMessage = '<li class="list-group-item text-center text-secondary p-4">Tidak ada data.</li>';
            const loadingMessage = '<li class="list-group-item text-center p-4"><div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div></li>';

            // Referensi elemen DOM
            const typesList = document.getElementById('attack-types-summary');
            const attackersList = document.getElementById('top-attackers-summary');
            const targetsList = document.getElementById('top-targets-summary');
            const latestLogsList = document.getElementById('latest-logs-list');
            const totalAttacksElement = document.getElementById('total-attacks-stat');
            const totalSensorsElement = document.getElementById('total-sensors-stat');

            async function fetchSummary() {
                try {
                    const response = await fetch('get_guest_summary.php');
                     if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                     const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) { const textResponse = await response.text(); throw new Error(`Respons bukan JSON: ${textResponse}`); }
                    const summary = await response.json();
                    if (!summary) return; // Keluar jika data kosong

                    // Update Statistik Utama
                    if (totalAttacksElement) totalAttacksElement.innerText = escapeHTML(summary.stats?.total_attacks ?? '0');
                    if (totalSensorsElement) totalSensorsElement.innerText = escapeHTML(summary.stats?.total_sensors ?? '0');

                    // Update Log Aktivitas Terbaru
                    if (latestLogsList) {
                        if (summary.latest_logs && summary.latest_logs.length > 0) {
                            latestLogsList.innerHTML = summary.latest_logs.map(log => `
                                <li class="list-group-item">
                                    <strong class="text-accent">${escapeHTML((log.attack_type || '').replace(/_/g, ' '))}</strong>
                                    <small class="d-block text-secondary">
                                        <i class="far fa-clock"></i> ${escapeHTML(new Date(log.timestamp).toLocaleString('id-ID'))} |
                                        <i class="fas fa-map-marker-alt"></i> ${escapeHTML(log.sensor_name || 'Sensor Tidak Dikenal')}
                                    </small>
                                </li>`).join('');
                        } else {
                            latestLogsList.innerHTML = noDataMessage;
                        }
                    }

                    // Update Ringkasan Serangan
                    if (typesList) typesList.innerHTML = Object.keys(summary.attack_types || {}).length > 0 ? Object.entries(summary.attack_types).map(([type, count]) => `<li class="list-group-item d-flex justify-content-between align-items-center">${escapeHTML(type.replace(/_/g, ' '))}<span class="badge bg-secondary rounded-pill">${escapeHTML(count)}</span></li>`).join('') : noDataMessage;
                    if (attackersList) attackersList.innerHTML = summary.top_attackers && summary.top_attackers.length > 0 ? summary.top_attackers.map(attacker => `<li class="list-group-item d-flex justify-content-between align-items-center"><code>${escapeHTML(attacker.mac)}</code><span class="badge bg-danger rounded-pill">${escapeHTML(attacker.count)}</span></li>`).join('') : noDataMessage;
                    if (targetsList) targetsList.innerHTML = summary.top_targets && summary.top_targets.length > 0 ? summary.top_targets.map(target => `<li class="list-group-item d-flex justify-content-between align-items-center">${escapeHTML(target.ssid)}<span class="badge bg-primary rounded-pill">${escapeHTML(target.count)}</span></li>`).join('') : noDataMessage;

                    // Update Peta Sensor Publik
                    if (publicMap && summary.sensors && summary.sensors.length > 0) {
                        // Hapus marker lama
                        Object.values(sensorMarkersPublic).forEach(marker => publicMap.removeLayer(marker));
                        sensorMarkersPublic = {};

                        summary.sensors.forEach(sensor => {
                             if (sensor.latitude && sensor.longitude && !isNaN(parseFloat(sensor.latitude)) && !isNaN(parseFloat(sensor.longitude))) {
                                const latLng = [parseFloat(sensor.latitude), parseFloat(sensor.longitude)];
                                // Gunakan marker sederhana untuk publik
                                const marker = L.circleMarker(latLng, {
                                    radius: 6,
                                    fillColor: "var(--accent-color)",
                                    color: "#fff",
                                    weight: 1,
                                    opacity: 1,
                                    fillOpacity: 0.8
                                }).addTo(publicMap);
                                marker.bindPopup(`<b>${escapeHTML(sensor.name)}</b>`);
                                sensorMarkersPublic[escapeHTML(sensor.name)] = marker; // Simpan marker
                            }
                        });
                        // Atur view peta agar mencakup semua marker (jika ada lebih dari 1)
                        if (summary.sensors.length > 1) {
                            const group = new L.featureGroup(Object.values(sensorMarkersPublic));
                            publicMap.fitBounds(group.getBounds().pad(0.5));
                        } else if (summary.sensors.length === 1 && summary.sensors[0].latitude && summary.sensors[0].longitude) {
                            // Jika hanya 1 sensor, center peta ke sensor tsb
                             publicMap.setView([parseFloat(summary.sensors[0].latitude), parseFloat(summary.sensors[0].longitude)], 10);
                        }
                    }

                } catch (error) {
                    console.error("Gagal mengambil data ringkasan publik:", error);
                    // Tampilkan pesan error di semua bagian ringkasan
                    const errorMsg = `<li class="list-group-item text-center text-danger p-4">Gagal memuat data.</li>`;
                    if (typesList) typesList.innerHTML = errorMsg;
                    if (attackersList) attackersList.innerHTML = errorMsg;
                    if (targetsList) targetsList.innerHTML = errorMsg;
                    if (latestLogsList) latestLogsList.innerHTML = errorMsg;
                    if (totalAttacksElement) totalAttacksElement.innerText = 'Error';
                    if (totalSensorsElement) totalSensorsElement.innerText = 'Error';
                }

                // Jadwalkan pembaruan berikutnya
                setTimeout(fetchSummary, 30000); // Perbarui setiap 30 detik
            }

            // Panggil fungsi untuk pertama kali
            fetchSummary();
        });
    </script>
</body>
</html>

