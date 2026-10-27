const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const root = path.resolve(__dirname, '..');
const scratch = path.join(root, 'storage/audit/tmp');
fs.mkdirSync(scratch, { recursive: true });
process.env.TMPDIR = scratch;
const { chromium } = require('/home/ubuntu/.hermes/profiles/dev/cache/scratch/pw/node_modules/playwright-core');
const baseURL = 'http://127.0.0.1:8765';
const executablePath = '/home/ubuntu/.cache/ms-playwright/chromium_headless_shell-1208/chrome-headless-shell-linux64/chrome-headless-shell';

function mockCamera(mode) {
    const mock = window.cameraMock = { requests: 0, starts: 0, stops: 0, tracks: [], mode };
    if (!navigator.mediaDevices) Object.defineProperty(navigator, 'mediaDevices', { value: {} });
    Object.defineProperty(navigator.mediaDevices, 'getUserMedia', { configurable: true, writable: true, value: async () => {
        mock.requests++;
        if (mode === 'denied') throw new DOMException('Permission denied', 'NotAllowedError');
        if (mode === 'no-device') throw new DOMException('No device', 'NotFoundError');
        if (mode === 'late') await new Promise(resolve => { mock.release = resolve; });
        const stream = new MediaStream();
        const tracks = [0, 1].map(() => ({ stopped: false, stop() { this.stopped = true; } }));
        mock.tracks.push(...tracks);
        stream.getTracks = () => tracks;
        return stream;
    } });
    if (mode === 'insecure') Object.defineProperty(window, 'isSecureContext', { value: false });
    if (mode === 'missing') return;
    window.Html5Qrcode = class {
        constructor(id) { this.reader = document.getElementById(id); }
        async start(constraints, options, callback) {
            mock.starts++;
            mock.decode = callback;
            const stream = await navigator.mediaDevices.getUserMedia({ video: constraints });
            const video = document.createElement('video');
            video.srcObject = stream;
            this.reader.append(video);
        }
        async stop() { mock.stops++; }
        clear() { this.reader.replaceChildren(); }
    };
}

(async () => {
    assert.ok(process.env.FISHPRO_PW, 'Set FISHPRO_PW before running');
    const browser = await chromium.launch({ executablePath, downloadsPath: scratch, artifactsDir: scratch, env: { ...process.env, TMPDIR: scratch } });
    try {
        const login = await browser.newContext({ baseURL });
        const page = await login.newPage();
        await page.goto('/login');
        await page.locator('input[name="email"]').fill('admin@fishpro.com');
        await page.locator('input[name="password"]').fill(process.env.FISHPRO_PW);
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('button[type="submit"]').click()]);
        await page.evaluate(() => { localStorage.removeItem('fishpro.trace.recent'); });
        const storageState = await login.storageState();
        await login.close();
        let checks = 0;
        async function scenario(mode, run, viewport = { width: 1440, height: 900 }, colorScheme = 'light') {
            const context = await browser.newContext({ baseURL, storageState, viewport, colorScheme });
            try {
                await context.addInitScript(mockCamera, mode);
                await context.route('https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js', route => route.fulfill({ contentType: 'application/javascript', body: '' }));
                const p = await context.newPage();
                const errors = [];
                p.on('pageerror', error => errors.push(error.message));
                await p.goto('/trace');
                assert.equal(await p.evaluate(() => cameraMock.requests), 0, 'No camera permission before click');
                assert.equal(await p.locator('label[for="manualCode"]').count(), 1);
                assert.equal(await p.locator('#scanFeedback').getAttribute('aria-live'), 'polite');
                await run(p);
                assert.deepEqual(errors, []);
                checks++;
                console.log(`PASS ${mode} ${viewport.width}px ${colorScheme}`);
            } finally { await context.close(); }
        }
        async function allStopped(p) {
            await p.waitForFunction(() => cameraMock.tracks.length === 2 && cameraMock.tracks.every(t => t.stopped));
            await p.waitForFunction(() => document.querySelectorAll('#qrReader video').length === 0);
        }
        async function running(p) {
            await p.locator('#toggleCameraBtn').click();
            await p.waitForFunction(() => document.getElementById('toggleCameraBtn').getAttribute('aria-pressed') === 'true');
        }
        await scenario('normal', async p => {
            await p.evaluate(() => {
                document.getElementById('toggleCameraBtn').click();
                document.getElementById('toggleCameraBtn').click();
            });
            await p.waitForFunction(() => document.getElementById('toggleCameraBtn').getAttribute('aria-pressed') === 'true');
            assert.equal(await p.evaluate(() => cameraMock.starts), 1, 'Double toggle must start once');
            await p.locator('#toggleCameraBtn').click();
            await allStopped(p);
            await p.evaluate(() => window.scannerStop());
            assert.equal(await p.locator('#toggleCameraBtn').isEnabled(), true);
        });
        await scenario('late', async p => {
            await p.locator('#toggleCameraBtn').click();
            await p.waitForFunction(() => !!cameraMock.release);
            await p.evaluate(() => { window.scannerStop(); cameraMock.release(); });
            await allStopped(p);
            await p.waitForFunction(() => !document.getElementById('toggleCameraBtn').disabled);
            assert.equal(await p.locator('#toggleCameraBtn').getAttribute('aria-pressed'), 'false');
            assert.equal(await p.locator('#toggleCameraBtn').isEnabled(), true);
        });
        for (const event of ['pagehide', 'hidden', 'success']) {
            await scenario('normal', async p => {
                await running(p);
                await p.evaluate(event => {
                    if (event === 'pagehide') window.dispatchEvent(new Event('pagehide'));
                    if (event === 'hidden') {
                        Object.defineProperty(document, 'hidden', { configurable: true, value: true });
                        document.dispatchEvent(new Event('visibilitychange'));
                    }
                    if (event === 'success') {
                        document.getElementById('resolveForm').submit = () => {};
                        cameraMock.decode('  01234567  ');
                    }
                }, event);
                await allStopped(p);
                if (event === 'success') assert.equal(await p.locator('#resolveCode').inputValue(), '01234567');
            });
        }
        for (const mode of ['denied', 'no-device']) {
            await scenario(mode, async p => {
                await p.locator('#toggleCameraBtn').click();
                await p.waitForFunction(() => !!document.getElementById('scanFeedback').textContent);
                assert.match(await p.locator('#scanFeedback').textContent(), mode === 'denied' ? /Izin kamera ditolak/ : /Kamera tidak ditemukan/);
                assert.equal(await p.locator('#toggleCameraBtn').isEnabled(), true);
            });
        }
        for (const width of [390, 1440]) {
            for (const theme of ['light', 'dark']) {
                await scenario('missing', async p => {
                    await p.evaluate(theme => { document.documentElement.dataset.bsTheme = theme; }, theme);
                    assert.match(await p.locator('#cameraUnavailable').textContent(), /Pustaka.*gagal dimuat/);
                    await p.evaluate(() => { window.receivedCodes = []; window.onScanCode = code => receivedCodes.push(code); });
                    await p.locator('#manualCode').fill('  RM-1/P03  ');
                    await p.locator('#manualCode').press('Enter');
                    await p.locator('#manualCode').fill('  https://evil.example/trace/batch/01234567  ');
                    await p.locator('#manualSubmit').click();
                    assert.deepEqual(await p.evaluate(() => receivedCodes), ['RM-1/P03', 'https://evil.example/trace/batch/01234567']);
                    await p.screenshot({ path: path.join(scratch, `qr-${width}-${theme}.png`), fullPage: true });
                }, { width, height: 900 }, theme);
            }
        }
        await scenario('normal', async p => {
            await p.goto('/stage/cutting/create');
            const requests = [];
            await p.route('**/trace/resolve', async route => {
                const code = route.request().postDataJSON().code;
                requests.push(code);
                await route.fulfill({ status: code.startsWith('https://evil.example') ? 422 : 200, contentType: 'application/json', body: JSON.stringify(code.startsWith('https://evil.example') ? { message: 'Alamat QR bukan dari aplikasi ini.' } : { type: 'batch', qr_code: '01234567' }) });
            });
            let lookups = 0;
            await p.route('**/api/batch/lookup/**', async route => {
                lookups++;
                assert.equal(new URL(route.request().url()).pathname, '/api/batch/lookup/01234567');
                await route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
            });
            await p.locator('#manualCode').fill('RM-1/P03');
            await p.locator('#manualCode').press('Enter');
            await p.waitForFunction(() => /tidak dikenali/.test(document.getElementById('scanFeedback').textContent));
            assert.equal(lookups, 1);
            await p.locator('#manualCode').fill('https://evil.example/trace/batch/01234567');
            await p.locator('#manualSubmit').click();
            await p.waitForFunction(() => /Alamat QR/.test(document.getElementById('scanFeedback').textContent));
            assert.equal(lookups, 1);
            assert.deepEqual(requests, ['RM-1/P03', 'https://evil.example/trace/batch/01234567']);
        });
        const insecure = await browser.newContext({ baseURL, storageState });
        try {
            await insecure.addInitScript(mockCamera, 'insecure');
            await insecure.route('https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js', route => route.fulfill({ body: '' }));
            await insecure.route('http://fishpro.invalid/**', async route => {
                const response = await insecure.request.get(baseURL + new URL(route.request().url()).pathname);
                await route.fulfill({ response });
            });
            const p = await insecure.newPage();
            await p.goto('http://fishpro.invalid/trace');
            assert.match(await p.locator('#cameraUnavailable').textContent(), /HTTPS/);
            assert.equal(await p.evaluate(() => cameraMock.requests), 0);
            checks++;
            console.log('PASS insecure context fallback');
        } finally { await insecure.close(); }
        console.log(`${checks} browser scenarios passed. No business writes; camera streams mocked.`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
