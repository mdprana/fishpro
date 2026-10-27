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
        <h6 class="fw-bold mb-0"><i class="bi bi-upc-scan me-2" aria-hidden="true"></i>{{ $scannerTitle }}</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="toggleCameraBtn"
                aria-controls="cameraWrap" aria-pressed="false" style="display:none">
            <i class="bi bi-camera me-1" aria-hidden="true"></i>Nyalakan Kamera
        </button>
    </div>

    <p class="text-muted small mb-2">{{ $scannerHint }}</p>

    <div id="cameraWrap" style="display:none">
        <div id="qrReader" class="mx-auto mb-2" style="max-width:340px"></div>
    </div>

    <div id="cameraUnavailable" class="alert alert-warning py-2 px-3 small mb-2" role="alert" style="display:none"></div>

    <label for="manualCode" class="form-label small mb-1">Kode batch, token QR, atau kode box</label>
    <div class="input-group">
        <span class="input-group-text bg-white" aria-hidden="true"><i class="bi bi-upc"></i></span>
        <input type="text" id="manualCode" class="form-control" autocomplete="off" maxlength="512"
               aria-describedby="scanFeedback"
               placeholder="Scan dengan alat, atau ketik kode lalu tekan Enter">
        <button type="button" class="btn btn-primary" id="manualSubmit">Cari</button>
    </div>
    <div id="scanFeedback" class="small mt-2" role="status" aria-live="polite"></div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const input    = document.getElementById('manualCode');
    const feedback = document.getElementById('scanFeedback');
    const btn      = document.getElementById('toggleCameraBtn');
    const wrap     = document.getElementById('cameraWrap');
    const notice   = document.getElementById('cameraUnavailable');
    let lastCode   = '';
    let lastAt     = 0;

    window.scanFeedback = function (message, type = 'muted') {
        feedback.className = 'small mt-2 text-' + type;
        feedback.textContent = message;
    };

    function handle(code, fromCamera) {
        code = (code || '').trim();
        if (!code) return;

        // Kamera membaca frame berulang kali; abaikan kode sama dalam 2 detik.
        const now = Date.now();
        if (fromCamera && code === lastCode && now - lastAt < 2000) return;
        lastCode = code; lastAt = now;

        if (typeof window.onScanCode === 'function') window.onScanCode(code);
        input.value = '';
        input.focus();
    }

    document.getElementById('manualSubmit').addEventListener('click', () => handle(input.value, false));
    input.addEventListener('keydown', e => {
        // Scanner USB mengirim Enter di akhir; perlakukan sama dengan klik Cari.
        if (e.key === 'Enter') { e.preventDefault(); handle(input.value, false); }
    });
    input.focus();

    // ── Kamera ────────────────────────────────────────────────
    function unavailable(message) {
        notice.textContent = message + ' Gunakan scanner USB atau ketik kodenya di bawah.';
        notice.style.display = '';
    }

    const secure = window.isSecureContext || ['localhost', '127.0.0.1'].includes(location.hostname);
    const media  = navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function';

    window.scannerStop = function () {};
    if (!secure) return unavailable('Kamera hanya bisa dipakai lewat HTTPS atau localhost.');
    if (typeof window.Html5Qrcode !== 'function') return unavailable('Pustaka pemindai kamera gagal dimuat.');
    if (!media) return unavailable('Browser ini tidak mendukung akses kamera.');

    let state   = 'idle';
    let gen     = 0;
    let pending = null;
    let closing = null;
    let scanner = null;
    const streams = new Set();

    const md = navigator.mediaDevices;
    const origGUM = md.getUserMedia.bind(md);
    md.getUserMedia = async function (constraints) {
        const myGen  = gen;
        const stream = await origGUM(constraints);
        if (myGen !== gen || state !== 'starting' && state !== 'running') {
            stream.getTracks().forEach(t => t.stop());
        } else {
            streams.add(stream);
        }
        return stream;
    };

    function killTracks() {
        streams.forEach(s => s.getTracks().forEach(t => t.stop()));
        streams.clear();
        wrap.querySelectorAll('video').forEach(v => {
            if (v.srcObject && v.srcObject.getTracks) v.srcObject.getTracks().forEach(t => t.stop());
            v.srcObject = null;
        });
    }

    async function releaseScanner(s) {
        if (!s) return;
        try { await s.stop(); } catch (e) {}
        try { await s.clear(); } catch (e) {}
        document.getElementById('qrReader').replaceChildren();
    }

    function render() {
        const on = state === 'running';
        btn.disabled = state === 'starting' || pending !== null || closing !== null;
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.setAttribute('aria-busy', btn.disabled ? 'true' : 'false');
        btn.innerHTML = on
            ? '<i class="bi bi-camera-video-off me-1" aria-hidden="true"></i>Matikan Kamera'
            : (state === 'starting'
                ? '<i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Membuka Kamera…'
                : '<i class="bi bi-camera me-1" aria-hidden="true"></i>Nyalakan Kamera');
        wrap.style.display = state === 'idle' ? 'none' : '';
    }

    function cameraError(err) {
        const s = String((err && (err.name || '')) + ' ' + (err && err.message || err));
        if (/NotAllowed|Permission|SecurityError/i.test(s)) return 'Izin kamera ditolak. Izinkan kamera di pengaturan browser, atau pakai scanner USB/ketik kode.';
        if (/NotFound|DevicesNotFound|Overconstrained|Requested device not found/i.test(s)) return 'Kamera tidak ditemukan di perangkat ini. Pakai scanner USB atau ketik kode.';
        if (/NotReadable|TrackStart|in use/i.test(s)) return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu atau ketik kode.';
        return 'Kamera tidak bisa dibuka. Pakai scanner USB atau ketik kode.';
    }

    function start() {
        if (state !== 'idle' || pending || closing) return;
        const myGen = gen;
        state = 'starting';
        render();
        let s;

        pending = (async () => {
            try {
                s = scanner = new Html5Qrcode('qrReader', false);
                await s.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 220, height: 220 } },
                    text => { if (myGen === gen) handle(text, true); },
                    () => {}  // frame tanpa QR itu normal, jangan bising
                );
                if (myGen !== gen) { await releaseScanner(s); killTracks(); return; }
                state = 'running';
                window.scanFeedback('Kamera aktif. Arahkan ke QR pada label.', 'muted');
            } catch (err) {
                await releaseScanner(s);
                if (myGen !== gen) return;
                killTracks();
                state = 'idle';
                window.scanFeedback(cameraError(err), 'danger');
            }
        })().finally(() => { pending = null; render(); });
    }

    async function stop() {
        gen++;
        const s = scanner;
        scanner = null;
        const wasStarting = state === 'starting';
        state = 'idle';
        killTracks();
        render();
        if (!wasStarting && s && !closing) {
            closing = releaseScanner(s).finally(() => {
                closing = null;
                killTracks();
                render();
            });
            render();
            await closing;
        }
        killTracks();
    }

    window.scannerStop = stop;
    btn.style.display = '';
    render();

    btn.addEventListener('click', () => (state === 'running' ? stop() : start()));
    window.addEventListener('pagehide', stop);
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
})();
</script>
@endpush
