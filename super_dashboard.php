<?php
session_start();
// Keamanan: Pastikan SUPER ADMIN sudah login
if (!isset($_SESSION['super_admin_id'])) {
    header('Location: super_login.php');
    exit;
}
// Ambil username untuk sapaan
$super_admin_username = $_SESSION['super_admin_username'] ?? 'Super Admin';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin Dashboard - WiCanary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #f4f7fa; font-family: 'Inter', sans-serif; }
        .navbar { box-shadow: 0 2px 4px rgba(0,0,0,.05); }
        .card { border: 1px solid #e0e5eb; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        .table-responsive { max-height: 600px; /* Sesuaikan tinggi tabel */ overflow-y: auto; overflow-x: auto; }
        .badge.bg-success { background-color: #198754 !important; }
        .badge.bg-danger { background-color: #dc3545 !important; }
        .badge.bg-secondary { background-color: #6c757d !important; }
        .card-header-sensor {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-white sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-broadcast-tower me-2 text-primary"></i>
                WiCanary Admin 
                <span class="badge bg-danger">Super Admin</span>
            </a>
            <div>
                <span class="navbar-text me-3">
                    Halo, <?php echo htmlspecialchars($super_admin_username); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid p-4">

        <div class="card mb-4">
            <div class="card-header bg-white border-0 pt-3 card-header-sensor">
                <h5><i class="fas fa-users me-2"></i>Manajemen Klien</h5>
                
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addClientModal">
                    <i class="fas fa-plus me-1"></i> Tambah Klien Baru
                </button>

            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nama Perusahaan</th>
                                <th>Email</th>
                                <th>Paket</th>
                                <th>Batas Sensor</th>
                                <th>Akhir Langganan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="client-list-body">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <footer class="text-center py-4"><p class="text-muted small">&copy; 2025 Budi Wibowo - ITBU</p></footer>

    
    <div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editClientModalLabel">Edit Data Klien</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editClientForm">
                    <div class="modal-body">
                        <input type="hidden" id="clientIdInput" name="client_id">
                        <div id="editClientError" class="alert alert-danger" style="display: none;" role="alert"></div>
                        <div class="mb-3"><label for="clientNameInput" class="form-label">Nama Perusahaan</label><input type="text" class="form-control" id="clientNameInput" name="company_name" required></div>
                        <div class="mb-3"><label for="clientEmailInput" class="form-label">Email Kontak</label><input type="email" class="form-control" id="clientEmailInput" name="email" required></div>
                        <div class="mb-3"><label for="clientPackageInput" class="form-label">Paket Langganan</label><select class="form-select" id="clientPackageInput" name="package_id" required></select></div>
                         <div class="mb-3"><label for="clientSubEndInput" class="form-label">Akhir Langganan (YYYY-MM-DD)</label><input type="date" class="form-control" id="clientSubEndInput" name="subscription_end_date"></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="clientIsActiveInput" name="is_active" value="1"><label class="form-check-label" for="clientIsActiveInput">Akun Aktif</label></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addClientModalLabel">Tambah Klien Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addClientForm">
                    <div class="modal-body">
                        <div id="addClientError" class="alert alert-danger" style="display: none;" role="alert"></div>
                        
                        <h6 class="text-muted">Data Perusahaan</h6>
                        <div class="mb-3"><label for="addClientNameInput" class="form-label">Nama Perusahaan</label><input type="text" class="form-control" id="addClientNameInput" name="company_name" required></div>
                        <div class="mb-3"><label for="addClientEmailInput" class="form-label">Email Kontak</label><input type="email" class="form-control" id="addClientEmailInput" name="email" required></div>
                        <div class="mb-3"><label for="addClientPackageInput" class="form-label">Paket Langganan</label><select class="form-select" id="addClientPackageInput" name="package_id" required></select></div>
                        <div class="mb-3"><label for="addClientSubEndInput" class="form-label">Akhir Langganan (Opsional)</label><input type="date" class="form-control" id="addClientSubEndInput" name="subscription_end_date"></div>
                        
                        <hr>
                        <h6 class="text-muted">Buat Akun Admin untuk Klien Ini</h6>
                        <div class="mb-3"><label for="addAdminUsernameInput" class="form-label">Username Admin</label><input type="text" class="form-control" id="addAdminUsernameInput" name="admin_username" required></div>
                        <div class="mb-3"><label for="addAdminPasswordInput" class="form-label">Password Admin</label><input type="password" class="form-control" id="addAdminPasswordInput" name="admin_password" required></div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Klien Baru</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Fungsi untuk escape HTML, mencegah XSS
        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        document.addEventListener('DOMContentLoaded', () => {
            
            // ===================================================================
            // = LOGIKA UNTUK SUPER ADMIN
            // ===================================================================
            
            // Variabel untuk Modal Edit Klien
            const clientTableBody = document.getElementById('client-list-body');
            const editClientModalEl = document.getElementById('editClientModal');
            const editClientModal = editClientModalEl ? new bootstrap.Modal(editClientModalEl) : null;
            const editClientForm = document.getElementById('editClientForm');
            const editClientErrorDiv = document.getElementById('editClientError');
            const clientPackageInput = document.getElementById('clientPackageInput');
            
            let allPackages = []; // Simpan data paket

            async function fetchSuperAdminData() {
                try {
                    const response = await fetch('get_all_clients.php');
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const data = await response.json();
                    if (data.error) throw new Error(data.error);

                    // 1. Isi tabel klien
                    if (clientTableBody) {
                        clientTableBody.innerHTML = '';
                        if (data.clients && data.clients.length > 0) {
                            data.clients.forEach(client => {
                                const statusBadge = client.is_active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Non-Aktif</span>';
                                const editButton = `
                                    <button class="btn btn-sm btn-outline-primary edit-client-btn"
                                        data-bs-toggle="modal" data-bs-target="#editClientModal"
                                        data-client-id="${escapeHTML(client.id)}"
                                        data-company-name="${escapeHTML(client.company_name)}"
                                        data-email="${escapeHTML(client.email)}"
                                        data-package-id="${escapeHTML(client.package_id)}"
                                        data-sub-end-date="${escapeHTML(client.subscription_end_date || '')}"
                                        data-is-active="${escapeHTML(client.is_active)}">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>`;
                                clientTableBody.innerHTML += `<tr>
                                    <td>${escapeHTML(client.id)}</td>
                                    <td>${escapeHTML(client.company_name)}</td>
                                    <td>${escapeHTML(client.email)}</td>
                                    <td>${escapeHTML(client.package_name || 'N/A')}</td>
                                    <td>${escapeHTML(client.max_sensors || 'N/A')}</td>
                                    <td>${escapeHTML(client.subscription_end_date || 'N/A')}</td>
                                    <td>${statusBadge}</td>
                                    <td>${editButton}</td>
                                </tr>`;
                            });
                        } else {
                            clientTableBody.innerHTML = '<tr><td colspan="8" class="text-center">Belum ada klien terdaftar.</td></tr>';
                        }
                    }
                    
                    // 2. Simpan data paket untuk dropdown
                    if (data.packages && data.packages.length > 0) {
                        allPackages = data.packages;
                    }

                } catch (error) {
                    console.error("Gagal mengambil data super admin:", error);
                    if (clientTableBody) clientTableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Gagal memuat data klien: ${escapeHTML(error.message)}</td></tr>`;
                }
            }
            
            // Isi modal EDIT klien
            if (editClientModalEl) {
                editClientModalEl.addEventListener('show.bs.modal', event => {
                    try {
                        const button = event.relatedTarget;
                        const getAttr = (name) => button.getAttribute(`data-${name}`) || '';
                        
                        // Isi dropdown paket
                        if (clientPackageInput) {
                            clientPackageInput.innerHTML = '';
                            const currentPackageId = getAttr('package-id');
                            allPackages.forEach(pkg => {
                                const selected = (pkg.id == currentPackageId) ? ' selected' : '';
                                clientPackageInput.innerHTML += `<option value="${escapeHTML(pkg.id)}"${selected}>${escapeHTML(pkg.name)}</option>`;
                            });
                        }
                        
                        // Isi field form lainnya
                        editClientModalEl.querySelector('#clientIdInput').value = getAttr('client-id');
                        editClientModalEl.querySelector('#clientNameInput').value = getAttr('company-name');
                        editClientModalEl.querySelector('#clientEmailInput').value = getAttr('email');
                        editClientModalEl.querySelector('#clientSubEndInput').value = getAttr('sub-end-date');
                        editClientModalEl.querySelector('#clientIsActiveInput').checked = (getAttr('is-active') == 1);
                        if (editClientErrorDiv) editClientErrorDiv.style.display = 'none';
                    } catch (e) {
                         console.error("Error saat mengisi modal edit klien:", e); alert("Gagal memuat detail klien.");
                    }
                });
            }
            
            // Submit form EDIT klien
            if (editClientForm) {
                editClientForm.addEventListener('submit', async event => {
                    event.preventDefault();
                    if (editClientErrorDiv) editClientErrorDiv.style.display = 'none';
                    const formData = new FormData(editClientForm);
                    
                    try {
                        const response = await fetch('update_client.php', { method: 'POST', body: formData });
                        const result = await response.json();
                        
                        if (response.ok && result.status === 'success') {
                            alert('Data klien berhasil diperbarui!');
                            if (editClientModal) editClientModal.hide();
                            fetchSuperAdminData(); // Muat ulang tabel
                        } else {
                            const errorMsg = result.message || 'Error tidak diketahui';
                            if (editClientErrorDiv) { editClientErrorDiv.textContent = errorMsg; editClientErrorDiv.style.display = 'block'; }
                            else { alert('Gagal: ' + errorMsg); }
                        }
                    } catch (error) {
                         console.error('Error saat update klien:', error);
                         if (editClientErrorDiv) { editClientErrorDiv.textContent = 'Terjadi kesalahan koneksi.'; editClientErrorDiv.style.display = 'block'; }
                         else { alert('Terjadi kesalahan koneksi.'); }
                    }
                });
            }

            // --- [PERBAIKAN] Logika untuk Modal TAMBAH KLIEN ---
            const addClientModalEl = document.getElementById('addClientModal');
            const addClientModal = addClientModalEl ? new bootstrap.Modal(addClientModalEl) : null;
            const addClientForm = document.getElementById('addClientForm');
            const addClientErrorDiv = document.getElementById('addClientError');
            const addClientPackageInput = document.getElementById('addClientPackageInput');

            // Isi dropdown paket saat modal "Tambah" dibuka
            if (addClientModalEl) {
                addClientModalEl.addEventListener('show.bs.modal', event => {
                    try {
                        if (addClientPackageInput) {
                            addClientPackageInput.innerHTML = '<option value="">-- Pilih Paket --</option>'; // Tambah opsi default
                            allPackages.forEach(pkg => {
                                addClientPackageInput.innerHTML += `<option value="${escapeHTML(pkg.id)}">${escapeHTML(pkg.name)}</option>`;
                            });
                        }
                        if (addClientErrorDiv) addClientErrorDiv.style.display = 'none';
                        if (addClientForm) addClientForm.reset(); // Kosongkan form setiap kali dibuka
                    } catch (e) {
                         console.error("Error saat mengisi modal tambah klien:", e); alert("Gagal memuat daftar paket.");
                    }
                });
            }

            // Submit form tambah klien
            if (addClientForm) {
                addClientForm.addEventListener('submit', async event => {
                    event.preventDefault();
                    if (addClientErrorDiv) addClientErrorDiv.style.display = 'none';
                    const formData = new FormData(addClientForm);
                    
                    try {
                        const response = await fetch('create_client.php', { method: 'POST', body: formData });
                        const result = await response.json();
                        
                        if (response.ok && result.status === 'success') {
                            alert('Klien baru dan admin klien berhasil dibuat!');
                            if (addClientModal) addClientModal.hide();
                            fetchSuperAdminData(); // Muat ulang tabel
                        } else {
                            const errorMsg = result.message || 'Error tidak diketahui';
                            if (addClientErrorDiv) { addClientErrorDiv.textContent = errorMsg; addClientErrorDiv.style.display = 'block'; }
                            else { alert('Gagal: ' + errorMsg); }
                        }
                    } catch (error) {
                         console.error('Error saat tambah klien:', error);
                         if (addClientErrorDiv) { addClientErrorDiv.textContent = 'Terjadi kesalahan koneksi.'; addClientErrorDiv.style.display = 'block'; }
                         else { alert('Terjadi kesalahan koneksi.'); }
                    }
                });
            }
            // ----------------------------------------

            // Panggil fetch data untuk Super Admin
            fetchSuperAdminData();
            
        });
    </script>
</body>
</html>