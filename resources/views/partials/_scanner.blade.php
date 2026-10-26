{{--
    Panel scan kode batch/box yang dipakai ulang di form tiap tahap & telusur.

    Tiga jalur masuk, karena satu saja tidak cukup di lantai produksi:
      1. Kamera (html5-qrcode), HANYA jalan di HTTPS atau localhost.
         Di http://192.168.x.x browser menolak akses kamera tanpa pesan jelas,
         jadi tombolnya disembunyikan otomatis dan diberi keterangan.
      2. Scanner barcode USB (keyboard wedge), mengetik cepat lalu Enter.
      3. Ketik manual, jaring pengaman terakhir saat label rusak.

    Halaman pemanggil cukup menyediakan fungsi global `onScanCode(code)`.

    Parameter opsional:
      $scannerTitle  judul panel
      $scannerHint   keterangan tambahan
--}}
@php
    $scannerTitle = $scannerTitle ?? 'Scan Kode Batch';
    $scannerHint  = $scannerHint  ?? 'Arahkan kamera ke QR pada label, atau ketik nomor batch-nya.';
@endphp

<div class="form-card mb-3" id="scannerPanel">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0"><i class="bi bi-upc-scan me-2"></i>{{ $scannerTitle }}</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="toggleCameraBtn" style="display:none">
            <i class="bi bi-camera me-1"></i>Nyalakan Kamera
        </button>
    </div>

    <p class="text-muted small mb-2">{{ $scannerHint }}</p>

    <div id="cameraWrap" style="display:none">
        <div id="qrReader" class="mx-auto mb-2" style="max-width:340px"></div>
    </div>

    <div id="cameraUnavailable" class="alert alert-warning py-2 px-3 small mb-2" style="display:none">
        <i class="bi bi-info-circle me-1"></i>
        Kamera hanya bisa dipakai lewat <strong>HTTPS</strong> atau <strong>localhost</strong>.
        Gunakan scanner USB atau ketik kodenya di bawah.
    </div>

    <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-upc"></i></span>
        <input type="text" id="manualCode" class="form-control" autocomplete="off"
               placeholder="Scan dengan alat, atau ketik kode lalu tekan Enter">
        <button type="button" class="btn btn-primary" id="manualSubmit">Cari</button>
    </div>
    <div id="scanFeedback" class="small mt-2"></div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const input    = document.getElementById('manualCode');
    const feedback = document.getElementById('scanFeedback');
    let lastCode   = '';
    let lastAt     = 0;

    window.scanFeedback = function (message, type = 'muted') {
        feedback.className = 'small mt-2 text-' + type;
        feedback.textContent = message;
    };

    function handle(code) {
        code = (code || '').trim();
        if (!code) return;

        // Kamera membaca frame berulang kali; abaikan kode sama dalam 2 detik.
        const now = Date.now();
        if (code === lastCode && now - lastAt < 2000) return;
        lastCode = code; lastAt = now;

        // Kamera HP sering membaca URL penuh, jadi ambil ruas terakhirnya.
        // Hanya untuk URL: nomor batch sendiri memuat garis miring (…-001/P03).
        if (/^https?:\/\//i.test(code)) code = code.substring(code.lastIndexOf('/') + 1);

        if (typeof window.onScanCode === 'function') {
            window.onScanCode(code);
        }
        input.value = '';
        input.focus();
    }

    document.getElementById('manualSubmit').addEventListener('click', () => handle(input.value));
    input.addEventListener('keydown', e => {
        // Scanner USB mengirim Enter di akhir; perlakukan sama dengan klik Cari.
        if (e.key === 'Enter') { e.preventDefault(); handle(input.value); }
    });
    input.focus();

    // ── Kamera ────────────────────────────────────────────────
    const secure = window.isSecureContext ||
                   ['localhost', '127.0.0.1'].includes(location.hostname);

    if (!secure) {
        document.getElementById('cameraUnavailable').style.display = '';
        return;
    }

    const btn  = document.getElementById('toggleCameraBtn');
    const wrap = document.getElementById('cameraWrap');
    btn.style.display = '';

    let scanner = null;
    let running = false;

    btn.addEventListener('click', async () => {
        if (running) {
            await scanner.stop();
            running = false;
            wrap.style.display = 'none';
            btn.innerHTML = '<i class="bi bi-camera me-1"></i>Nyalakan Kamera';
            return;
        }

        wrap.style.display = '';
        scanner = scanner || new Html5Qrcode('qrReader');

        try {
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 220, height: 220 } },
                text => handle(text),
                () => {}  // frame tanpa QR itu normal, jangan bising
            );
            running = true;
            btn.innerHTML = '<i class="bi bi-camera-video-off me-1"></i>Matikan Kamera';
        } catch (err) {
            wrap.style.display = 'none';
            window.scanFeedback('Kamera tidak bisa dibuka: ' + err, 'danger');
        }
    });
})();
</script>
@endpush
