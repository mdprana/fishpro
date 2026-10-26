{{--
    Render QR di sisi browser (qrcode.js dari CDN, konsisten dengan Bootstrap
    & Chart.js yang sudah dipakai). Tidak ada package PHP tambahan.

    Pakai: <div class="qr" data-qr="{{ $url }}" data-size="120"></div>
    lalu @include('partials._qr') SATU KALI di halaman tersebut.
--}}
@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
(function renderAllQr() {
    function draw() {
        document.querySelectorAll('.qr:not([data-qr-done])').forEach(el => {
            const value = el.dataset.qr;
            if (!value) return;

            const size = parseInt(el.dataset.size || '110', 10);
            el.innerHTML = '';

            try {
                new QRCode(el, {
                    text: value,
                    width: size,
                    height: size,
                    // Level H tahan sampai ~30% permukaan rusak. Label di lantai
                    // produksi kena air, es, dan gesekan keranjang.
                    correctLevel: QRCode.CorrectLevel.H,
                });
                el.setAttribute('data-qr-done', '1');
            } catch (e) {
                el.innerHTML = '<div class="text-danger small">QR gagal dibuat</div>';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', draw);
    } else {
        draw();
    }

    // Supaya QR yang ditambahkan lewat JS (mis. box baru ditimbang) ikut tergambar.
    window.renderQrCodes = draw;
})();
</script>
@endpush
@endonce
