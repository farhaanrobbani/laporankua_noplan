// ==UserScript==
// @name         SIMKAH4 — Otomasi KUA
// @namespace    laporan-kua
// @version      3.0.0
// @description  Satu panel untuk BIMWIN, unduh massal bukti bayar, dan surat tugas manten di SIMKAH4 memakai sesi login aktif. Konfigurasi diambil dari aplikasi Laporan.
// @match        https://simkah4.kemenag.go.id/*
// @grant        GM_getValue
// @grant        GM_setValue
// @grant        unsafeWindow
// @run-at       document-idle
// @noframes
// ==/UserScript==

(function () {
    'use strict';

    var pageWindow = typeof unsafeWindow !== 'undefined' ? unsafeWindow : window;
    var ENDPOINT_KEYS = ['cek', 'dialog', 'simpan', 'selesai'];
    var STORAGE_KEY = 'laporan-otomasi-config';
    var LEGACY_STORAGE_KEY = 'laporan-bimwin-config';

    function emptyEndpoints() {
        return { cek: '', dialog: '', simpan: '', selesai: '' };
    }

    function normalizeEndpoints(value) {
        var endpoints = emptyEndpoints();
        if (!value || typeof value !== 'object' || Array.isArray(value)) return endpoints;
        ENDPOINT_KEYS.forEach(function (key) {
            endpoints[key] = typeof value[key] === 'string' ? value[key] : '';
        });
        return endpoints;
    }

    function endpointsComplete(endpoints) {
        return ENDPOINT_KEYS.every(function (key) {
            return typeof endpoints[key] === 'string' && endpoints[key] !== '';
        });
    }

    var DEFAULTS = {
        appUrl: '',
        bimwinUrl: '',
        endpoints: emptyEndpoints(),
        provinsi: '',
        kabkota: '',
        kecamatan: '',
        fasilitatorSuami: '',
        fasilitatorIstri: '',
        maxCoba: 10,
        redamNotif: false
    };

    var running = false;
    var stopRequested = false;
    var bulkRunning = false;
    var bulkStopRequested = false;
    var recording = false;
    var recordedEndpoints = emptyEndpoints();
    var recordedAppUrl = '';
    var recordedBimwinUrl = '';
    var recordingPageWindow = null;
    var recordingFetch = null;
    var recordingXhrOpen = null;
    var recordingXhrSend = null;

    function sleep(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    function getPageJQuery() {
        var candidates = [pageWindow, window];
        for (var i = 0; i < candidates.length; i++) {
            try {
                if (candidates[i] && candidates[i].jQuery) return candidates[i].jQuery;
            } catch (e) {}
        }
        return null;
    }

    async function waitForJQuery(timeoutMs) {
        var timeout = typeof timeoutMs === 'number' ? timeoutMs : 10000;
        var started = Date.now();
        var jq = getPageJQuery();
        while (!jq && Date.now() - started < timeout) {
            await sleep(200);
            jq = getPageJQuery();
        }
        return jq;
    }

    function norm(value) {
        return String(value || '').toUpperCase().replace(/\s+/g, ' ').trim();
    }

    function clampInt(value, min, max, fallback) {
        var number = parseInt(value, 10);
        if (isNaN(number)) return fallback;
        return Math.min(max, Math.max(min, number));
    }

    function normalizeAppUrl(value) {
        try {
            var parsed = new URL(String(value || '').trim());
            if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return '';
            parsed.search = '';
            parsed.hash = '';
            return (parsed.origin + parsed.pathname).replace(/\/+$/, '');
        } catch (e) {
            return '';
        }
    }

    function bimwinPageUrl(value) {
        var base = normalizeAppUrl(value);
        if (!base) return '';
        if (/\/otomasi\/bimwin$/i.test(base) || /\/bimwin$/i.test(base)) return base;
        if (/\/otomasi$/i.test(base)) return base + '/bimwin';
        return base + '/otomasi/bimwin';
    }

    function appUrlFromBimwinUrl(value) {
        return normalizeAppUrl(value)
            .replace(/\/otomasi\/bimwin$/i, '')
            .replace(/\/bimwin$/i, '');
    }

    function configFromCode() {
        var raw = (panelElement('bw-config-code')?.value || '').trim();
        if (!raw) return null;
        try {
            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return null;
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function appUrlFromConfigCode() {
        var parsed = configFromCode();
        return normalizeAppUrl(parsed && parsed.appUrl);
    }

    function bimwinUrlFromConfigCode() {
        var parsed = configFromCode();
        var direct = normalizeAppUrl(parsed && parsed.bimwinUrl);
        if (direct) return direct;
        var appUrl = normalizeAppUrl(parsed && parsed.appUrl);
        return appUrl ? bimwinPageUrl(appUrl) : '';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function loadStoredConfig() {
        var stored = null;
        try {
            if (typeof GM_getValue === 'function') stored = GM_getValue(STORAGE_KEY, null);
        } catch (e) {
            stored = null;
        }
        if (!stored) {
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                stored = raw ? JSON.parse(raw) : null;
            } catch (e) {
                stored = null;
            }
        }
        if (!stored) {
            try {
                if (typeof GM_getValue === 'function') stored = GM_getValue(LEGACY_STORAGE_KEY, null);
            } catch (e) {
                stored = null;
            }
        }
        if (!stored) {
            try {
                var legacyRaw = localStorage.getItem(LEGACY_STORAGE_KEY);
                stored = legacyRaw ? JSON.parse(legacyRaw) : null;
            } catch (e) {
                stored = null;
            }
        }
        if (typeof stored === 'string') {
            try {
                stored = JSON.parse(stored);
            } catch (e) {
                stored = null;
            }
        }
        var merged = Object.assign({}, DEFAULTS, stored || {});
        merged.endpoints = normalizeEndpoints(merged.endpoints);
        return merged;
    }

    function storeConfig(config) {
        try {
            if (typeof GM_setValue === 'function') GM_setValue(STORAGE_KEY, config);
        } catch (e) {}
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(config));
        } catch (e) {}
    }

    function resetConfig() {
        if (running || bulkRunning) {
            log('Hentikan proses Otomasi dulu sebelum reset.', 'err');
            return;
        }
        if (!window.confirm('Reset semua konfigurasi Otomasi di browser? Endpoint, URL Laporan, wilayah, fasilitator, dan pengaturan akan dihapus.')) {
            return;
        }
        if (recording) stopRecording(true);

        recordedAppUrl = '';
        recordedBimwinUrl = '';
        recordedEndpoints = emptyEndpoints();
        var defaults = Object.assign({}, DEFAULTS);
        defaults.endpoints = emptyEndpoints();
        writeConfigToPanel(defaults);
        storeConfig(readConfigFromPanel());
        if (panelElement('bw-config-code')) panelElement('bw-config-code').value = '';
        if (panelElement('bw-mode')) panelElement('bw-mode').value = 'cek';
        progress('');
        log('Semua konfigurasi Otomasi di browser direset.', 'ok');
    }

    function panelElement(id) {
        return document.getElementById(id);
    }

    function log(message, type) {
        var line = '[' + new Date().toLocaleTimeString() + '] ' + message;
        console.log('[BIMWIN] ' + message);
        var el = panelElement('bw-log');
        if (!el) return;
        var lines = el.textContent ? el.textContent.split('\n') : [];
        lines.push(line);
        if (lines.length > 300) lines = lines.slice(-300);
        el.textContent = lines.join('\n');
        el.scrollTop = el.scrollHeight;
        if (type === 'err') console.error('[BIMWIN] ' + message);
        if (type === 'warn') console.warn('[BIMWIN] ' + message);
    }

    function progress(message) {
        var el = panelElement('bw-progress');
        if (el) el.textContent = message || '';
    }

    function readConfigFromPanel() {
        return {
            appUrl: recordedAppUrl,
            bimwinUrl: recordedBimwinUrl,
            endpoints: normalizeEndpoints(recordedEndpoints),
            provinsi: (panelElement('bw-provinsi')?.value || '').trim(),
            kabkota: (panelElement('bw-kabkota')?.value || '').trim(),
            kecamatan: (panelElement('bw-kecamatan')?.value || '').trim(),
            fasilitatorSuami: (panelElement('bw-fas-suami')?.value || '').trim(),
            fasilitatorIstri: (panelElement('bw-fas-istri')?.value || '').trim(),
            maxCoba: clampInt(panelElement('bw-max-coba')?.value, 1, 30, 10),
            redamNotif: !!panelElement('bw-redam')?.checked
        };
    }

    function writeConfigToPanel(config) {
        var merged = Object.assign({}, DEFAULTS, config || {});
        merged.endpoints = normalizeEndpoints(merged.endpoints);
        var nextAppUrl = normalizeAppUrl(merged.appUrl);
        var nextBimwinUrl = normalizeAppUrl(merged.bimwinUrl);
        if (nextAppUrl) recordedAppUrl = nextAppUrl;
        if (nextBimwinUrl) {
            recordedBimwinUrl = nextBimwinUrl;
            if (!nextAppUrl) recordedAppUrl = appUrlFromBimwinUrl(nextBimwinUrl);
        } else if (nextAppUrl) {
            recordedBimwinUrl = bimwinPageUrl(nextAppUrl);
        }
        recordedEndpoints = merged.endpoints;
        renderEndpointStatus();
        if (panelElement('bw-provinsi')) panelElement('bw-provinsi').value = merged.provinsi || '';
        if (panelElement('bw-kabkota')) panelElement('bw-kabkota').value = merged.kabkota || '';
        if (panelElement('bw-kecamatan')) panelElement('bw-kecamatan').value = merged.kecamatan || '';
        if (panelElement('bw-fas-suami')) panelElement('bw-fas-suami').value = merged.fasilitatorSuami || '';
        if (panelElement('bw-fas-istri')) panelElement('bw-fas-istri').value = merged.fasilitatorIstri || '';
        if (panelElement('bw-max-coba')) panelElement('bw-max-coba').value = clampInt(merged.maxCoba, 1, 30, 10);
        if (panelElement('bw-redam')) panelElement('bw-redam').checked = !!merged.redamNotif;
    }

    function renderEndpointStatus() {
        var count = ENDPOINT_KEYS.filter(function (key) {
            return recordedEndpoints[key] !== '';
        }).length;
        var status = panelElement('bw-endpoint-status');
        if (status) {
            status.textContent = count + '/4';
            status.style.color = count === 4 ? '#047857' : '#b45309';
        }
        var list = panelElement('bw-endpoint-list');
        if (list) {
            list.textContent = ENDPOINT_KEYS.map(function (key) {
                return key + ': ' + (recordedEndpoints[key] ? 'OK' : '—');
            }).join(' · ');
        }
        var appStatus = panelElement('bw-app-status');
        if (appStatus) {
            appStatus.textContent = recordedAppUrl ? 'Laporan: ' + recordedAppUrl : 'Laporan: belum terhubung';
            appStatus.style.color = recordedAppUrl ? '#047857' : '#b45309';
        }
        var complete = endpointsComplete(recordedEndpoints);
        ['bw-send-endpoints', 'bw-copy-endpoints'].forEach(function (id) {
            var button = panelElement(id);
            if (button) button.disabled = !complete;
        });
        var recordButton = panelElement('bw-record');
        if (recordButton) {
            recordButton.textContent = recording ? 'Stop rekam' : 'Rekam endpoint';
            recordButton.classList.toggle('is-recording', recording);
        }
    }

    function cleanEndpoint(url) {
        try {
            var parsed = new URL(url, pageWindow.location.href);
            if (parsed.protocol !== 'https:' || parsed.hostname !== 'simkah4.kemenag.go.id') return '';
            if (parsed.pathname === '' || parsed.pathname === '/') return '';
            parsed.search = '';
            parsed.hash = '';
            return parsed.href;
        } catch (e) {
            return '';
        }
    }

    function bodyToText(body) {
        if (!body) return '';
        if (typeof body === 'string') return body;

        if (typeof body.forEach === 'function' && typeof body.append === 'function') {
            var pairs = [];
            body.forEach(function (value, key) {
                if (typeof value === 'string') {
                    pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
                }
            });
            return pairs.join('&');
        }

        if (typeof body.toString === 'function') {
            var text = body.toString();
            if (text && text !== '[object Object]' && text !== '[object FormData]' && text !== '[object URLSearchParams]') {
                return text;
            }
        }

        return '';
    }

    function classifyRequest(method, url, body) {
        var verb = String(method || 'GET').toUpperCase();
        var text = bodyToText(body);

        if (verb === 'POST') {
            if (text.includes('value=SS_ALL')) return 'selesai';
            if (text.includes('catin=') && text.includes('no_daftar=')) return 'cek';
            if (/id_(provinsi|kabkota|kecamatan|jadwal)_(suami|istri)=/.test(text)) return 'simpan';
            return null;
        }

        if (verb === 'GET') {
            try {
                var parsed = new URL(url, pageWindow.location.href);
                if (parsed.searchParams.has('no_daftar')) return 'dialog';
            } catch (e) {}
        }

        return null;
    }

    function recordRequest(method, url, body) {
        if (!recording) return;

        var key = classifyRequest(method, url, body);
        if (!key) return;

        var endpoint = cleanEndpoint(url);
        if (!endpoint || recordedEndpoints[key] === endpoint) return;

        recordedEndpoints[key] = endpoint;
        renderEndpointStatus();
        storeConfig(readConfigFromPanel());
        log('Endpoint ' + key + ' terekam.', 'ok');

        if (endpointsComplete(recordedEndpoints)) {
            stopRecording(true);
            log('4/4 endpoint lengkap. Klik Kirim ke Laporan.', 'ok');
        }
    }

    function installRecorder() {
        recordingPageWindow = pageWindow;
        var installed = false;

        try {
            if (typeof recordingPageWindow.fetch === 'function') {
                recordingFetch = recordingPageWindow.fetch;
                recordingPageWindow.fetch = function (input, init) {
                    var originalFetch = recordingFetch;
                    try {
                        var url = typeof input === 'string' ? input : (input && input.url);
                        var method = (init && init.method) || (input && input.method) || 'GET';
                        var body = init && init.body;
                        recordRequest(method, url, body);
                    } catch (e) {}
                    return originalFetch.apply(this, arguments);
                };
                installed = true;
            }
        } catch (e) {
            recordingFetch = null;
            log('Gagal memasang perekam fetch halaman: ' + e.message, 'err');
        }

        try {
            var Xhr = recordingPageWindow.XMLHttpRequest;
            if (Xhr && Xhr.prototype) {
                recordingXhrOpen = Xhr.prototype.open;
                recordingXhrSend = Xhr.prototype.send;
                Xhr.prototype.open = function (method, url) {
                    var originalOpen = recordingXhrOpen;
                    this.__bwRequest = { method: method, url: url };
                    return originalOpen.apply(this, arguments);
                };
                Xhr.prototype.send = function (body) {
                    var originalSend = recordingXhrSend;
                    try {
                        if (this.__bwRequest) {
                            recordRequest(this.__bwRequest.method, this.__bwRequest.url, body);
                        }
                    } catch (e) {}
                    return originalSend.apply(this, arguments);
                };
                installed = true;
            }
        } catch (e) {
            recordingXhrOpen = null;
            recordingXhrSend = null;
            log('Gagal memasang perekam XHR halaman: ' + e.message, 'err');
        }

        if (!installed) {
            recordingPageWindow = null;
        }

        return installed;
    }

    function stopRecording(silent) {
        if (!recording) {
            renderEndpointStatus();
            return;
        }

        recording = false;
        if (recordingPageWindow && recordingFetch) {
            try {
                recordingPageWindow.fetch = recordingFetch;
            } catch (e) {}
            recordingFetch = null;
        }
        if (recordingPageWindow && recordingXhrOpen) {
            try {
                recordingPageWindow.XMLHttpRequest.prototype.open = recordingXhrOpen;
            } catch (e) {}
            recordingXhrOpen = null;
        }
        if (recordingPageWindow && recordingXhrSend) {
            try {
                recordingPageWindow.XMLHttpRequest.prototype.send = recordingXhrSend;
            } catch (e) {}
            recordingXhrSend = null;
        }
        recordingPageWindow = null;
        renderEndpointStatus();
        if (!silent) log('Perekaman endpoint dihentikan.', 'info');
    }

    function startRecording() {
        if (recording) {
            stopRecording(false);
            return;
        }
        if (running) {
            log('Hentikan proses BIMWIN dulu sebelum merekam endpoint.', 'err');
            return;
        }

        var hasAny = ENDPOINT_KEYS.some(function (key) {
            return recordedEndpoints[key] !== '';
        });
        if (hasAny && !window.confirm('Kosongkan endpoint terekam dan mulai rekam ulang?')) {
            return;
        }

        if (typeof unsafeWindow === 'undefined') {
            log('unsafeWindow tidak tersedia; perekaman mungkin tidak menangkap request halaman.', 'warn');
        }

        recordedEndpoints = emptyEndpoints();
        recording = true;
        storeConfig(readConfigFromPanel());

        if (!installRecorder()) {
            recording = false;
            renderEndpointStatus();
            log('Perekam endpoint gagal dipasang. Isi endpoint manual di halaman Laporan.', 'err');
            return;
        }

        renderEndpointStatus();
        log('Perekam endpoint terpasang. Lakukan satu alur BIMWIN manual sampai 4/4.', 'info');
    }

    function copyText(text) {
        return new Promise(function (resolve) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () {
                    resolve(true);
                }, function () {
                    resolve(false);
                });
                return;
            }

            var area = document.createElement('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            var ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (e) {}
            document.body.removeChild(area);
            resolve(ok);
        });
    }

    function ensureAppUrl() {
        if (recordedAppUrl || recordedBimwinUrl) {
            if (!recordedAppUrl) recordedAppUrl = appUrlFromBimwinUrl(recordedBimwinUrl);
            if (!recordedBimwinUrl) recordedBimwinUrl = bimwinPageUrl(recordedAppUrl);
            return recordedAppUrl;
        }

        var codeAppUrl = appUrlFromConfigCode();
        var codeBimwinUrl = bimwinUrlFromConfigCode();
        if (codeAppUrl || codeBimwinUrl) {
            if (!codeAppUrl && codeBimwinUrl) codeAppUrl = appUrlFromBimwinUrl(codeBimwinUrl);
            recordedAppUrl = codeAppUrl;
            recordedBimwinUrl = codeBimwinUrl || bimwinPageUrl(codeAppUrl);
            renderEndpointStatus();
            storeConfig(readConfigFromPanel());
            log('URL Laporan diambil dari kode konfigurasi.', 'ok');
            return recordedAppUrl;
        }

        var input = pageWindow.prompt('URL aplikasi Laporan (root, contoh: https://laporan.example.com):', '');
        if (input === null) return '';

        var normalized = normalizeAppUrl(input);
        if (!normalized) {
            log('URL Laporan tidak valid. Gunakan alamat http/https.', 'err');
            return '';
        }

        recordedAppUrl = normalized;
        recordedBimwinUrl = bimwinPageUrl(normalized);
        renderEndpointStatus();
        storeConfig(readConfigFromPanel());
        log('URL Laporan disimpan: ' + recordedAppUrl, 'ok');
        return recordedAppUrl;
    }

    async function sendEndpointsToLaporan() {
        if (!endpointsComplete(recordedEndpoints)) {
            log('Endpoint belum lengkap. Rekam sampai 4/4.', 'err');
            return;
        }

        var appUrl = ensureAppUrl();
        if (!appUrl && !recordedBimwinUrl) {
            log('URL Laporan belum ada. Salin kode konfigurasi dari Laporan, tempel di panel, lalu klik Muat.', 'err');
            return;
        }

        var bimwinUrl = recordedBimwinUrl || bimwinPageUrl(appUrl);
        if (!bimwinUrl) {
            log('URL halaman BIMWIN tidak valid.', 'err');
            return;
        }

        var url = bimwinUrl + '#endpoints=' + encodeURIComponent(JSON.stringify(recordedEndpoints));
        var opened = null;
        try {
            opened = pageWindow.open(url, '_blank');
        } catch (e) {
            log('Gagal membuka halaman Laporan: ' + e.message, 'err');
            return;
        }
        if (!opened) {
            log('Popup diblokir browser. Izinkan popup lalu coba lagi.', 'warn');
            return;
        }
        try {
            opened.opener = null;
        } catch (e) {}
        log('Membuka halaman Laporan untuk menyimpan endpoint.', 'ok');
    }

    async function copyEndpoints() {
        if (!endpointsComplete(recordedEndpoints)) {
            log('Endpoint belum lengkap. Rekam sampai 4/4.', 'err');
            return;
        }
        var ok = await copyText(JSON.stringify(recordedEndpoints, null, 2));
        log(ok ? 'JSON endpoint tersalin.' : 'Gagal menyalin JSON endpoint.', ok ? 'ok' : 'err');
    }

    var BULK_BASE = 'https://simkah4.kemenag.go.id';
    var BULK_DELAY_MS = 1200;
    var BULK_UUID_RE = /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i;
    var BULK_MODES = {
        bukti: {
            param: 'no_billing',
            uuidKey: 'sb-uuid-bukti',
            legacyKey: 'sb-uuid',
            listLabel: 'Daftar No Billing (satu per baris)',
            placeholder: '820260908847263 - Ahmad Fauzi\n820260908847264 - Siti Aminah',
            uuidPlaceholder: 'tempel URL bukti bayar / UUID',
            fileName: function (item) { return item.key + (item.nama ? ' - ' + item.nama : '') + '.pdf'; },
            countNoun: 'bukti bayar'
        },
        surat: {
            param: 'no_daftar',
            uuidKey: 'sb-uuid-surat',
            legacyKey: '',
            listLabel: 'Daftar Nomor Daftar (satu per baris)',
            placeholder: 'ND01003507061092026 - Budi dan Siti\nND01003507061092027 - Ahmad dan Aminah',
            uuidPlaceholder: 'tempel URL surat tugas / UUID',
            fileName: function (item) {
                var label = item.nama ? 'STM - ' + item.nama : 'STM - ' + item.key;
                return label + '.pdf';
            },
            countNoun: 'surat tugas'
        }
    };

    function bulkExtractUuid(value) {
        value = (value || '').trim();
        if (!value) return '';
        var match = value.match(BULK_UUID_RE);
        return match ? match[0] : '';
    }

    function bulkSanitizeNama(nama) {
        return (nama || '')
            .replace(/[\\/:*?"<>|]/g, ' ')
            // eslint-disable-next-line no-control-regex
            .replace(/[\x00-\x1f\x7f]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 80);
    }

    function bulkParseList(raw) {
        var out = [];
        var seen = {};
        (raw || '').split(/\r?\n/).forEach(function (line) {
            line = line.trim();
            if (!line) return;

            var dashIdx = line.indexOf(' - ');
            if (dashIdx !== -1) {
                var key = line.slice(0, dashIdx).trim();
                if (key && !seen[key]) {
                    seen[key] = true;
                    out.push({ key: key, nama: bulkSanitizeNama(line.slice(dashIdx + 3)) });
                }
                return;
            }

            if (/^[A-Za-z0-9]{8,}$/.test(line)) {
                if (!seen[line]) {
                    seen[line] = true;
                    out.push({ key: line, nama: '' });
                }
                return;
            }

            line.split(/[\s,;]+/).forEach(function (token) {
                if (/^[A-Za-z0-9]{8,}$/.test(token) && !seen[token]) {
                    seen[token] = true;
                    out.push({ key: token, nama: '' });
                }
            });
        });
        return out;
    }

    function bulkLog(message, line) {
        console.log('[Otomasi Bulk] ' + message);
        var el = panelElement('ot-bulk-log');
        if (!el) return;
        if (line) el.textContent += line + '\n';
        el.textContent += message + '\n';
        el.scrollTop = el.scrollHeight;
    }

    function bulkProgress(message) {
        var el = panelElement('ot-bulk-progress');
        if (el) el.textContent = message || '';
    }

    function bulkDownloadBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
    }

    function bulkModeOf() {
        var select = panelElement('ot-bulk-mode');
        return BULK_MODES[select && select.value === 'surat' ? 'surat' : 'bukti'];
    }

    function bulkUuidStorage(mode) {
        try {
            if (mode.legacyKey && !GM_getValue(mode.uuidKey, '') && GM_getValue(mode.legacyKey, '')) {
                GM_setValue(mode.uuidKey, GM_getValue(mode.legacyKey, ''));
            }
            return GM_getValue(mode.uuidKey, '');
        } catch (e) {
            return '';
        }
    }

    function refreshBulkPlaceholders() {
        var mode = bulkModeOf();
        var uuidEl = panelElement('ot-bulk-uuid');
        var listEl = panelElement('ot-bulk-list');
        var listLabel = panelElement('ot-bulk-list-label');
        var title = panelElement('ot-bulk-title');
        if (title) title.textContent = 'Unduh Massal ' + (mode === BULK_MODES.surat ? 'Surat Tugas Manten' : 'Bukti Bayar');
        if (uuidEl) uuidEl.placeholder = mode.uuidPlaceholder;
        if (listEl) listEl.placeholder = mode.placeholder;
        if (listLabel) listLabel.textContent = mode.listLabel;
        if (uuidEl) uuidEl.value = bulkUuidStorage(mode);
    }

    async function runBulk() {
        if (bulkRunning) return;

        var mode = bulkModeOf();
        var uuid = bulkExtractUuid(panelElement('ot-bulk-uuid').value) || bulkUuidStorage(mode);
        var list = bulkParseList(panelElement('ot-bulk-list').value);

        if (!uuid) {
            bulkProgress('UUID belum diisi — tempel URL dokumen dulu.');
            return;
        }
        if (!list.length) {
            bulkProgress('Daftar kosong — tempel daftar dulu.');
            return;
        }

        try {
            GM_setValue(mode.uuidKey, uuid);
        } catch (e) {}

        bulkRunning = true;
        bulkStopRequested = false;
        panelElement('ot-bulk-start').disabled = true;
        panelElement('ot-bulk-stop').disabled = false;
        bulkLog('Mulai unduh ' + list.length + ' ' + mode.countNoun + ' (UUID ' + uuid + ')', '=== ' + new Date().toLocaleTimeString() + ' ===');

        var ok = 0;
        var gagal = 0;

        for (var i = 0; i < list.length; i++) {
            if (bulkStopRequested) {
                bulkLog('Dihentikan pada ' + (i + 1) + '/' + list.length + '. Sisa bisa ditempel ulang.', '');
                break;
            }

            var item = list[i];
            var key = item.key;
            var fileName = mode.fileName(item);
            bulkProgress('[' + (i + 1) + '/' + list.length + '] ' + key);
            var url = BULK_BASE + '/' + uuid + '?' + mode.param + '=' + encodeURIComponent(key);

            try {
                var res = await fetch(url, { credentials: 'include', redirect: 'follow' });
                if (!res.ok) {
                    gagal++;
                    bulkLog('Gagal ' + key + ' — HTTP ' + res.status, '');
                    continue;
                }

                var ct = (res.headers.get('content-type') || '').toLowerCase();
                if (ct.indexOf('html') !== -1 || ct.indexOf('json') !== -1) {
                    bulkLog('Berhenti: respons bukan PDF (' + ct + '). Sesi login berakhir? Login ulang SIMKAH4 lalu jalankan lagi.', '');
                    break;
                }

                var blob = await res.blob();
                bulkDownloadBlob(blob, fileName);
                ok++;
                bulkLog('OK ' + fileName, '');
            } catch (err) {
                gagal++;
                bulkLog('Gagal ' + key + ' — ' + err, '');
            }

            await sleep(BULK_DELAY_MS);
        }

        bulkProgress('Selesai: ' + ok + ' berhasil, ' + gagal + ' gagal.');
        bulkLog('Selesai: ' + ok + ' berhasil, ' + gagal + ' gagal.', '');
        bulkRunning = false;
        bulkStopRequested = false;
        panelElement('ot-bulk-start').disabled = false;
        panelElement('ot-bulk-stop').disabled = true;
    }

    function panelHtml() {
        return ''
            + '<div id="bw-toggle" style="position:fixed;right:16px;left:auto;bottom:16px;z-index:999999;background:#4f46e5;color:#fff;border:none;border-radius:9999px;padding:8px 14px;font-size:12px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.3);font-family:sans-serif;">Otomasi</div>'
            + '<div id="bw-panel" style="display:none;position:fixed;right:16px;left:auto;bottom:56px;z-index:999999;width:420px;max-width:calc(100vw - 32px);max-height:calc(100vh - 80px);overflow:auto;background:#fff;color:#111;border:1px solid #bbb;border-radius:8px;padding:10px;box-shadow:0 4px 16px rgba(0,0,0,.3);font-family:sans-serif;">'
            +   '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">'
            +     '<strong style="font-size:13px;">Otomasi — SIMKAH4</strong>'
            +     '<button id="bw-hide" type="button" title="Tutup panel" style="border:none;background:none;cursor:pointer;font-size:14px;line-height:1;">✕</button>'
            +   '</div>'
            +   '<div id="ot-tab-bar" style="display:flex;gap:4px;margin:6px 0;">'
            +     '<button type="button" class="ot-tab is-active" data-ot-tab="bimwin" style="flex:1;padding:5px 6px;font-size:11px;cursor:pointer;">BIMWIN</button>'
            +     '<button type="button" class="ot-tab" data-ot-tab="bukti" style="flex:1;padding:5px 6px;font-size:11px;cursor:pointer;">Bukti Bayar</button>'
            +     '<button type="button" class="ot-tab" data-ot-tab="surat" style="flex:1;padding:5px 6px;font-size:11px;cursor:pointer;">Surat Tugas</button>'
            +   '</div>'
            +   '<details style="margin:6px 0;">'
            +     '<summary style="font-size:11px;cursor:pointer;">Kode konfigurasi dari Laporan</summary>'
            +     '<textarea id="bw-config-code" rows="4" placeholder=\'{"appUrl":"...","endpoints":{...}}\' style="width:100%;box-sizing:border-box;margin-top:4px;font-size:11px;padding:4px;"></textarea>'
            +     '<button id="bw-load-code" type="button" style="margin-top:4px;padding:4px 8px;font-size:11px;cursor:pointer;">Muat</button>'
            +   '</details>'
            +   '<div id="ot-tab-bimwin">'
            +     '<p style="font-size:10px;color:#555;margin:0 0 6px;">Memproses baris grid yang sedang tampil. Mode Cek saja tidak menulis data.</p>'
            +     '<div style="border:1px dashed #4f46e5;border-radius:4px;padding:6px;margin:6px 0;background:#eef2ff;">'
            +       '<div style="display:flex;justify-content:space-between;align-items:center;">'
            +         '<strong style="font-size:11px;">Endpoint akun ini</strong>'
            +         '<span id="bw-endpoint-status" style="font-size:11px;font-weight:bold;color:#b45309;">0/4</span>'
            +       '</div>'
            +       '<div id="bw-endpoint-list" style="font-size:10px;color:#555;margin-top:2px;">cek: — · dialog: — · simpan: — · selesai: —</div>'
            +       '<div id="bw-app-status" style="font-size:10px;color:#b45309;margin-top:2px;">Laporan: belum terhubung</div>'
            +       '<div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;">'
            +         '<button id="bw-record" type="button" style="padding:4px 8px;font-size:10px;cursor:pointer;">Rekam endpoint</button>'
            +         '<button id="bw-send-endpoints" type="button" disabled style="padding:4px 8px;font-size:10px;cursor:pointer;">Kirim ke Laporan</button>'
            +         '<button id="bw-copy-endpoints" type="button" disabled style="padding:4px 8px;font-size:10px;cursor:pointer;">Salin JSON</button>'
            +         '<button id="bw-reset" type="button" title="Hapus semua konfigurasi Otomasi di browser" style="padding:4px 8px;font-size:10px;cursor:pointer;">Reset</button>'
            +       '</div>'
            +       '<p style="font-size:10px;color:#555;margin:4px 0 0;">Klik <strong>Rekam endpoint</strong>, lalu lakukan satu alur BIMWIN manual. Simpan/selesai menulis ke SIMKAH4.</p>'
            +     '</div>'
            +     '<label for="bw-mode" style="font-size:11px;display:block;margin-bottom:2px;">Mode</label>'
            +     '<select id="bw-mode" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-size:12px;padding:4px;">'
            +       '<option value="cek">Cek saja (tidak menulis)</option>'
            +       '<option value="isi">Isi jadwal (tanpa tandai selesai)</option>'
            +       '<option value="lengkap">Isi + tandai selesai</option>'
            +     '</select>'
            +     '<details style="margin:6px 0;">'
            +       '<summary style="font-size:11px;cursor:pointer;">Pengaturan wilayah &amp; proses</summary>'
            +       '<p style="font-size:10px;color:#555;margin:4px 0;">Terisi otomatis dari kode konfigurasi Laporan. Sesuaikan hanya jika perlu.</p>'
            +       '<label for="bw-provinsi" style="font-size:11px;display:block;margin-bottom:2px;">Provinsi</label>'
            +       '<input id="bw-provinsi" type="text" placeholder="Nama provinsi" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-size:12px;padding:4px;" />'
            +       '<label for="bw-kabkota" style="font-size:11px;display:block;margin-bottom:2px;">Kabupaten/Kota</label>'
            +       '<input id="bw-kabkota" type="text" placeholder="Nama kabupaten/kota" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-size:12px;padding:4px;" />'
            +       '<label for="bw-kecamatan" style="font-size:11px;display:block;margin-bottom:2px;">Kecamatan</label>'
            +       '<input id="bw-kecamatan" type="text" placeholder="Nama kecamatan" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-size:12px;padding:4px;" />'
            +       '<div style="display:flex;gap:6px;">'
            +         '<label style="flex:1;font-size:11px;display:block;margin-bottom:2px;">Fasilitator suami<input id="bw-fas-suami" type="text" placeholder="opsional" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-size:12px;padding:4px;" /></label>'
            +         '<label style="flex:1;font-size:11px;display:block;margin-bottom:2px;">Fasilitator istri<input id="bw-fas-istri" type="text" placeholder="opsional" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-size:12px;padding:4px;" /></label>'
            +       '</div>'
            +       '<div style="display:flex;gap:6px;align-items:end;">'
            +         '<label style="flex:1;font-size:11px;display:block;margin-bottom:2px;">Max percobaan<input id="bw-max-coba" type="number" min="1" max="30" value="10" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-size:12px;padding:4px;" /></label>'
            +         '<label style="display:flex;align-items:center;gap:4px;font-size:11px;margin-bottom:6px;"><input id="bw-redam" type="checkbox" /> Redam notifikasi</label>'
            +       '</div>'
            +     '</details>'
            +     '<div style="display:flex;gap:6px;margin:6px 0;">'
            +       '<button id="bw-start" type="button" style="flex:1;padding:6px 8px;font-size:12px;cursor:pointer;">Mulai</button>'
            +       '<button id="bw-stop" type="button" disabled style="flex:1;padding:6px 8px;font-size:12px;cursor:pointer;">Berhenti</button>'
            +       '<button id="bw-save" type="button" style="padding:6px 8px;font-size:12px;cursor:pointer;">Simpan</button>'
            +     '</div>'
            +     '<div id="bw-progress" style="font-size:11px;margin-bottom:4px;"></div>'
            +     '<pre id="bw-log" style="max-height:150px;overflow:auto;font-size:10px;margin:0;white-space:pre-wrap;background:#f7f7f7;padding:4px;border-radius:4px;"></pre>'
            +     '<button id="bw-clear-log" type="button" style="margin-top:4px;padding:3px 6px;font-size:10px;cursor:pointer;">Bersihkan log</button>'
            +   '</div>'
            +   '<div id="ot-tab-bulk" style="display:none;">'
            +     '<p style="font-size:10px;color:#555;margin:0 0 6px;">Tempel daftar dari halaman Otomasi, lalu unduh PDF secara massal memakai sesi login SIMKAH4.</p>'
            +     '<strong id="ot-bulk-title" style="font-size:12px;display:block;margin-bottom:4px;">Unduh Massal Bukti Bayar</strong>'
            +     '<select id="ot-bulk-mode" style="display:none;"><option value="bukti">Bukti Bayar</option><option value="surat">Surat Tugas Manten</option></select>'
            +     '<label for="ot-bulk-uuid" style="font-size:11px;display:block;margin-bottom:2px;">UUID SIMKAH (sekali saja, tersimpan per jenis)</label>'
            +     '<input id="ot-bulk-uuid" type="text" placeholder="tempel URL dokumen / UUID" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-size:12px;padding:4px;" />'
            +     '<label id="ot-bulk-list-label" for="ot-bulk-list" style="font-size:11px;display:block;margin-bottom:2px;">Daftar No Billing (satu per baris)</label>'
            +     '<textarea id="ot-bulk-list" rows="6" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-size:12px;padding:4px;" placeholder="820260908847263 - Ahmad Fauzi\n820260908847264 - Siti Aminah"></textarea>'
            +     '<div style="display:flex;gap:6px;margin-bottom:6px;">'
            +       '<button id="ot-bulk-start" type="button" style="flex:1;padding:5px 8px;font-size:12px;cursor:pointer;">Mulai</button>'
            +       '<button id="ot-bulk-stop" type="button" disabled style="flex:1;padding:5px 8px;font-size:12px;cursor:pointer;">Berhenti</button>'
            +     '</div>'
            +     '<div id="ot-bulk-progress" style="font-size:11px;margin-bottom:4px;"></div>'
            +     '<pre id="ot-bulk-log" style="max-height:120px;overflow:auto;font-size:10px;margin:0;white-space:pre-wrap;background:#f7f7f7;padding:4px;border-radius:4px;"></pre>'
            +   '</div>'
            + '</div>';
    }

    function initPanel() {
        function removeById(id) {
            var el = panelElement(id);
            if (el) el.remove();
        }

        var existingToggle = panelElement('bw-toggle');
        if (existingToggle) {
            if (panelElement('ot-tab-bar')) return;
            removeById('bw-panel');
            removeById('bw-toggle');
        }

        removeById('sb-toggle');
        removeById('sb-panel');
        setTimeout(function () {
            removeById('sb-toggle');
            removeById('sb-panel');
        }, 1000);

        var style = document.createElement('style');
        style.textContent = [
            '#bw-panel{color-scheme:light;}',
            '#bw-panel,#bw-panel *{box-sizing:border-box;}',
            '#bw-panel button{border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;color:#111;font-family:inherit;line-height:1.2;}',
            '#bw-panel button:disabled{opacity:.5;cursor:default;}',
            '#bw-panel input,#bw-panel select,#bw-panel textarea{background:#fff;color:#111;border:1px solid #cbd5e1;border-radius:4px;font-family:inherit;}',
            '#bw-panel input::placeholder,#bw-panel textarea::placeholder{color:#94a3b8;}',
            '#bw-panel details{margin:6px 0;}',
            '#bw-panel summary{cursor:pointer;}',
            '#bw-panel .ot-tab{border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;color:#111;font-family:inherit;line-height:1.2;}',
            '#bw-panel .ot-tab.is-active{background:#4f46e5;color:#fff;border-color:#4f46e5;font-weight:600;}',
            '#bw-panel #bw-record{background:#4f46e5;color:#fff;border-color:#4f46e5;font-weight:600;}',
            '#bw-panel #bw-record.is-recording{background:#b91c1c;border-color:#b91c1c;}',
            '#bw-panel #bw-start,#bw-panel #ot-bulk-start{background:#4f46e5;color:#fff;border-color:#4f46e5;font-weight:600;}',
            '#bw-panel #bw-send-endpoints{background:#047857;color:#fff;border-color:#047857;}',
            '#bw-panel #bw-send-endpoints:disabled{background:#f8fafc;color:#111;border-color:#cbd5e1;}',
            '#bw-panel #bw-copy-endpoints,#bw-panel #bw-load-code{background:#eef2ff;color:#312e81;border-color:#c7d2fe;}',
            '#bw-panel #bw-reset{background:#b91c1c;color:#fff;border-color:#b91c1c;font-weight:600;}',
            '#bw-panel #bw-hide{border:none;background:none;color:#111;font-size:14px;}',
            '#bw-toggle{position:fixed!important;right:16px!important;left:auto!important;bottom:16px!important;}',
            '#bw-panel{position:fixed!important;right:16px!important;left:auto!important;}'
        ].join('');
        document.head.appendChild(style);

        var host = document.createElement('div');
        host.innerHTML = panelHtml();
        document.body.appendChild(host);

        function activateTab(name) {
            var isBimwin = name === 'bimwin';
            panelElement('ot-tab-bimwin').style.display = isBimwin ? 'block' : 'none';
            panelElement('ot-tab-bulk').style.display = isBimwin ? 'none' : 'block';

            if (!isBimwin) {
                panelElement('ot-bulk-mode').value = name === 'surat' ? 'surat' : 'bukti';
                refreshBulkPlaceholders();
            }

            var buttons = document.querySelectorAll('#ot-tab-bar [data-ot-tab]');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].classList.toggle('is-active', buttons[i].getAttribute('data-ot-tab') === name);
            }
        }

        panelElement('bw-toggle').addEventListener('click', function () {
            var panel = panelElement('bw-panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        });

        panelElement('bw-hide').addEventListener('click', function () {
            panelElement('bw-panel').style.display = 'none';
        });

        var tabButtons = document.querySelectorAll('#ot-tab-bar [data-ot-tab]');
        for (var i = 0; i < tabButtons.length; i++) {
            tabButtons[i].addEventListener('click', function () {
                activateTab(this.getAttribute('data-ot-tab'));
            });
        }

        panelElement('bw-start').addEventListener('click', run);
        panelElement('bw-stop').addEventListener('click', function () {
            stopRequested = true;
            log('Permintaan berhenti diterima. Proses berjalan akan diselesaikan lebih dulu.', 'warn');
        });
        panelElement('bw-save').addEventListener('click', function () {
            storeConfig(readConfigFromPanel());
            log('Konfigurasi disimpan di browser.', 'ok');
        });
        panelElement('bw-load-code').addEventListener('click', function () {
            var raw = (panelElement('bw-config-code')?.value || '').trim();
            if (!raw) {
                log('Kode konfigurasi masih kosong.', 'warn');
                return;
            }
            try {
                var parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                    throw new Error('Format harus objek JSON.');
                }
                writeConfigToPanel(parsed);
                storeConfig(readConfigFromPanel());
                log('Konfigurasi berhasil dimuat.', 'ok');
            } catch (e) {
                log('Kode konfigurasi tidak valid: ' + e.message, 'err');
            }
        });
        panelElement('bw-clear-log').addEventListener('click', function () {
            panelElement('bw-log').textContent = '';
        });
        panelElement('bw-record').addEventListener('click', startRecording);
        panelElement('bw-send-endpoints').addEventListener('click', sendEndpointsToLaporan);
        panelElement('bw-copy-endpoints').addEventListener('click', copyEndpoints);
        panelElement('bw-reset').addEventListener('click', resetConfig);

        panelElement('ot-bulk-start').addEventListener('click', runBulk);
        panelElement('ot-bulk-stop').addEventListener('click', function () {
            bulkStopRequested = true;
            bulkLog('Permintaan berhenti diterima. Proses berjalan akan diselesaikan lebih dulu.', '');
        });

        refreshBulkPlaceholders();
        activateTab('bimwin');
        writeConfigToPanel(loadStoredConfig());
    }

    function requestHeaders() {
        var csrf = csrfToken();
        if (!csrf) throw new Error('CSRF token SIMKAH4 tidak ditemukan. Muat ulang halaman.');
        return {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    async function fetchJson(url, body) {
        var response = await fetch(url, {
            method: 'POST',
            credentials: 'include',
            headers: requestHeaders(),
            body: body.toString()
        });

        if (response.redirected && /login/i.test(response.url)) {
            throw new Error('Sesi SIMKAH4 berakhir. Login ulang lalu jalankan lagi.');
        }
        if (!response.ok) {
            throw new Error('SIMKAH4 membalas HTTP ' + response.status + '.');
        }

        var text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Respons SIMKAH4 bukan JSON. Sesi login mungkin berakhir.');
        }
    }

    async function cekSatuCatin(noDaftar, catin, endpoints) {
        var body = new URLSearchParams();
        body.append('no_daftar', noDaftar);
        body.append('catin', catin);
        var result = await fetchJson(endpoints.cek, body);
        if (!result || !Array.isArray(result.items)) {
            throw new Error('Respons cek BIMWIN tidak dikenali.');
        }
        return result.items.length > 0;
    }

    async function cekBelum(endpoints) {
        if (!csrfToken()) throw new Error('CSRF token SIMKAH4 tidak ditemukan. Muat ulang halaman.');

        var grid = document.querySelector('#grid, #gridPencatatan');
        if (!grid) throw new Error('Grid SIMKAH4 tidak ditemukan. Buka halaman daftar nikah dulu.');

        var rows = grid.querySelectorAll('.dx-data-row');
        if (!rows.length) throw new Error('Tidak ada baris data pada grid SIMKAH4.');

        var hasil = [];
        var errors = [];
        var sudahDicek = {};

        for (var i = 0; i < rows.length; i++) {
            if (stopRequested) {
                log('Pengecekan dihentikan oleh pengguna.', 'warn');
                break;
            }

            var row = rows[i];
            var noUrut = row.getAttribute('aria-rowindex') || '?';
            var noDaftar = '';
            var indexes = ['4', '5'];
            for (var j = 0; j < indexes.length; j++) {
                var cell = row.querySelector('[aria-colindex="' + indexes[j] + '"]');
                if (cell && cell.innerText.trim().startsWith('ND')) {
                    noDaftar = cell.innerText.trim();
                    break;
                }
            }
            if (!noDaftar || sudahDicek[noDaftar]) continue;
            sudahDicek[noDaftar] = true;

            var tglEl = row.querySelector('[aria-colindex="11"]');
            var tglAkad = tglEl ? tglEl.innerText.trim() : '-';

            try {
                var status = {
                    SUAMI: await cekSatuCatin(noDaftar, 'SUAMI', endpoints),
                    ISTRI: await cekSatuCatin(noDaftar, 'ISTRI', endpoints)
                };
                var belum = [];
                if (!status.SUAMI) belum.push('suami');
                if (!status.ISTRI) belum.push('istri');

                if (belum.length) {
                    log('BELUM: [No. ' + noUrut + '] ' + tglAkad + ' | ' + noDaftar + ' (' + belum.join(', ') + ')', 'warn');
                    hasil.push({ noDaftar: noDaftar, noUrut: noUrut, tglAkad: tglAkad, belum: belum });
                } else {
                    log('AMAN: [No. ' + noUrut + '] ' + tglAkad + ' | ' + noDaftar, 'ok');
                }
            } catch (e) {
                errors.push({ noDaftar: noDaftar, message: e.message });
                log('Gagal cek: [No. ' + noUrut + '] ' + noDaftar + ' — ' + e.message, 'err');
            }
        }

        return { belum: hasil, errors: errors };
    }

    function jqueryGet(url, data) {
        var $ = getPageJQuery();
        if (!$) return Promise.reject(new Error('jQuery SIMKAH4 tidak tersedia. Muat ulang halaman.'));
        return new Promise(function (resolve, reject) {
            $.get(url, data).done(resolve).fail(function (xhr) {
                reject(new Error('Gagal memuat dialog (HTTP ' + (xhr && xhr.status) + ').'));
            });
        });
    }

    function jqueryPost(url, data) {
        var $ = getPageJQuery();
        if (!$) return Promise.reject(new Error('jQuery SIMKAH4 tidak tersedia. Muat ulang halaman.'));
        return new Promise(function (resolve, reject) {
            $.ajax({
                type: 'POST',
                url: url,
                data: data,
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).done(resolve).fail(function (xhr) {
                reject(new Error('Gagal menyimpan status selesai (HTTP ' + (xhr && xhr.status) + ').'));
            });
        });
    }

    function jqueryAjaxForm(formElement, endpoint) {
        var $ = getPageJQuery();
        if (!$) return Promise.reject(new Error('jQuery SIMKAH4 tidak tersedia. Muat ulang halaman.'));
        var formData = new FormData(formElement);
        return new Promise(function (resolve, reject) {
            $.ajax({
                type: 'POST',
                url: endpoint,
                enctype: 'multipart/form-data',
                cache: false,
                contentType: false,
                processData: false,
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).done(resolve).fail(function (xhr) {
                reject(new Error('Gagal menyimpan BIMWIN (HTTP ' + (xhr && xhr.status) + ').'));
            });
        });
    }

    async function bukaDialog(noDaftar, endpoint) {
        var $ = getPageJQuery();
        if (!$) throw new Error('jQuery SIMKAH4 tidak tersedia. Muat ulang halaman.');

        $('#bimwin-auto-wrap').remove();
        if ($('#bimwin').length) {
            throw new Error('Dialog BIMWIN lain masih terbuka. Tutup dulu lalu jalankan lagi.');
        }

        var html = await jqueryGet(endpoint, { no_daftar: noDaftar });
        var wrap = $('<div id="bimwin-auto-wrap" style="position:fixed;left:-99999px;top:0;width:1100px"></div>').appendTo('body');
        wrap.html(typeof html === 'string' ? html : (html.html || html.data || JSON.stringify(html)));

        for (var i = 0; i < 40; i++) {
            if ($('#bimwin').length && $('#bimwin').data('dxForm')) return true;
            await sleep(250);
        }

        return false;
    }

    function tutupDialog() {
        try {
            var $ = getPageJQuery();
            if ($) $('#bimwin-auto-wrap').remove();
        } catch (e) {}
    }

    async function loadItems(editor) {
        var items = [];
        for (var i = 0; i < 20; i++) {
            var dataSource = editor.getDataSource();
            if (dataSource) {
                dataSource.paginate(false);
                await dataSource.reload();
                items = dataSource.items();
                if (items.length) break;
            }
            await sleep(300);
        }
        return items;
    }

    async function pilih(form, field, cocok) {
        var editor = form.getEditor(field);
        if (!editor) {
            log('Field ' + field + ' tidak ditemukan pada form BIMWIN.', 'err');
            return false;
        }

        var items = await loadItems(editor);
        var item = items.find(function (candidate) {
            return cocok(norm(candidate.Name));
        });

        if (!item) {
            log('Tidak ditemukan pada ' + field + ': ' + items.map(function (candidate) { return candidate.Name; }).join(', '), 'err');
            return false;
        }

        editor.option('value', item.ID);
        await sleep(800);
        return true;
    }

    async function prosesBimwin(noDaftar, catinBelum, config, markComplete) {
        if (!await bukaDialog(noDaftar, config.endpoints.dialog)) {
            throw new Error('Dialog BIMWIN gagal dibuka.');
        }

        try {
            var $ = getPageJQuery();
            if (!$) throw new Error('jQuery SIMKAH4 tidak tersedia. Muat ulang halaman.');
            var form = $('#bimwin').dxForm('instance');
            if (!form) throw new Error('Form BIMWIN tidak siap.');

            var provinsi = norm(config.provinsi);
            var kabkota = norm(config.kabkota);
            var kecamatan = norm(config.kecamatan);

            for (var i = 0; i < 2; i++) {
                var catin = i === 0 ? 'suami' : 'istri';
                if (!await pilih(form, 'id_provinsi_' + catin, function (name) { return name === provinsi; })) {
                    throw new Error('Provinsi tidak cocok untuk ' + catin + '.');
                }
                if (!await pilih(form, 'id_kabkota_' + catin, function (name) { return name === kabkota; })) {
                    throw new Error('Kabupaten/kota tidak cocok untuk ' + catin + '.');
                }
                await sleep(1500);
                if (!await pilih(form, 'id_kecamatan_' + catin, function (name) { return name === kecamatan; })) {
                    throw new Error('Kecamatan tidak cocok untuk ' + catin + '.');
                }
            }

            var kandidat = { suami: [], istri: [] };
            var terpakai = { suami: new Set(), istri: new Set() };

            for (var c = 0; c < 2; c++) {
                var peran = c === 0 ? 'suami' : 'istri';
                var editor = form.getEditor('id_jadwal_' + peran);
                if (!editor) throw new Error('Field jadwal ' + peran + ' tidak ditemukan.');

                var items = await loadItems(editor);
                var kata = norm(peran === 'suami' ? config.fasilitatorSuami : config.fasilitatorIstri);
                var list = items
                    .filter(function (item) {
                        return (!kata || norm(item.Name).includes(kata)) && Number(item.Sisa) > 0;
                    })
                    .sort(function (a, b) {
                        return Number(b.Sisa) - Number(a.Sisa);
                    });

                kandidat[peran] = list;
                var current = editor.option('value');
                var currentKey = current === undefined || current === null ? '' : String(current);
                var currentItem = items.find(function (item) { return String(item.ID) === currentKey; });

                if (currentKey && currentItem && Number(currentItem.Sisa) > 0) {
                    terpakai[peran].add(currentKey);
                    log('  fasilitator ' + peran + ' => ' + currentItem.Name + ' (sudah terpilih)', 'ok');
                } else if (list.length) {
                    editor.option('value', list[0].ID);
                    terpakai[peran].add(String(list[0].ID));
                    log('  fasilitator ' + peran + ' => ' + list[0].Name, 'ok');
                } else {
                    throw new Error('Tidak ada fasilitator berkuota untuk ' + peran + '.');
                }

                await sleep(800);
            }

            var sukses = false;
            for (var attempt = 0; attempt < config.maxCoba; attempt++) {
                if (stopRequested) throw new Error('Dihentikan oleh pengguna sebelum menyimpan.');

                var result = await jqueryAjaxForm($('#bimwin')[0], config.endpoints.simpan);
                log('  simpan percobaan ' + (attempt + 1) + ': ' + JSON.stringify(result), 'info');

                if (result && Number(result.code) === 200) {
                    sukses = true;
                    break;
                }

                var pesan = norm(result && result.message);
                var catinError = pesan.includes('SUAMI') ? 'suami' : (pesan.includes('ISTRI') ? 'istri' : null);
                if (!catinError || !pesan.includes('KUOTA')) {
                    throw new Error('Gagal simpan: ' + (result && result.message ? result.message : 'respons tidak dikenali'));
                }

                var sisa = kandidat[catinError].filter(function (item) {
                    return !terpakai[catinError].has(String(item.ID));
                });
                if (!sisa.length) throw new Error('Kuota fasilitator habis untuk ' + catinError + '.');

                terpakai[catinError].add(String(sisa[0].ID));
                form.getEditor('id_jadwal_' + catinError).option('value', sisa[0].ID);
                log('  ganti jadwal ' + catinError + ' => ' + sisa[0].Name, 'warn');
                await sleep(1200);
            }

            if (!sukses) throw new Error('Batas percobaan simpan tercapai.');
            await sleep(1000);

            if (markComplete) {
                if (stopRequested) {
                    log('  Data tersimpan, tetapi tandai selesai dilewati karena dihentikan.', 'warn');
                    return true;
                }

                var noDaftarForm = form.getEditor('no_daftar')?.option('value') || noDaftar;
                for (var k = 0; k < catinBelum.length; k++) {
                    var peranSelesai = catinBelum[k];
                    var idJadwal = form.getEditor('id_jadwal_' + peranSelesai)?.option('value');
                    if (!idJadwal) {
                        log('  id_jadwal kosong untuk ' + peranSelesai + ', lewati tandai selesai.', 'warn');
                        continue;
                    }
                    var selesaiResult = await jqueryPost(config.endpoints.selesai, {
                        id_jadwal: idJadwal,
                        no_daftar: noDaftarForm,
                        catin: peranSelesai.toUpperCase(),
                        value: 'SS_ALL'
                    });
                    log('  tandai selesai ' + peranSelesai + ': ' + JSON.stringify(selesaiResult), 'ok');
                    await sleep(500);
                }
            }

            return true;
        } finally {
            tutupDialog();
        }
    }

    function refreshGrids() {
        try {
            var $ = getPageJQuery();
            if (!$) return;
            $('#detail, #grid, #gridPencatatan').each(function () {
                var grid = $(this).data('dxDataGrid');
                if (grid) grid.refresh();
            });
        } catch (e) {}
    }

    async function run() {
        if (running) return;

        var config = readConfigFromPanel();
        if (recording) {
            log('Hentikan perekaman endpoint dulu sebelum menjalankan BIMWIN.', 'err');
            return;
        }
        if (!endpointsComplete(config.endpoints)) {
            log('Endpoint BIMWIN belum lengkap. Rekam sampai 4/4 lalu simpan di Laporan.', 'err');
            return;
        }
        if (!config.provinsi || !config.kabkota || !config.kecamatan) {
            log('Provinsi, kabupaten/kota, dan kecamatan wajib diisi.', 'err');
            return;
        }
        if (!await waitForJQuery(10000)) {
            var grid = document.querySelector('#grid, #gridPencatatan');
            log('jQuery SIMKAH4 tidak ditemukan. ' + (grid ? 'Grid terdeteksi; coba muat ulang halaman.' : 'Buka halaman daftar nikah SIMKAH4 lalu jalankan lagi.'), 'err');
            return;
        }

        var mode = panelElement('bw-mode')?.value || 'cek';
        var writeMode = mode !== 'cek';
        var notifOriginal = null;

        running = true;
        stopRequested = false;
        storeConfig(config);
        panelElement('bw-start').disabled = true;
        panelElement('bw-stop').disabled = false;

        if (config.redamNotif && pageWindow.SimKah && typeof pageWindow.SimKah.WindowNotif === 'function') {
            notifOriginal = pageWindow.SimKah.WindowNotif;
            pageWindow.SimKah.WindowNotif = function (title, message, status) {
                log('[notif] ' + title + ' — ' + message + ' — ' + status, 'warn');
            };
        }

        log('Mulai mode ' + mode + ' untuk ' + config.provinsi + ' / ' + config.kabkota + ' / ' + config.kecamatan, 'info');

        try {
            var hasil = await cekBelum(config.endpoints);
            if (stopRequested) {
                log('Dihentikan setelah pengecekan.', 'warn');
                return;
            }

            if (!hasil.belum.length) {
                if (hasil.errors.length) {
                    log('Tidak ada data yang bisa diproses. ' + hasil.errors.length + ' data gagal dicek.', 'warn');
                } else {
                    log('Semua data pada grid sudah lengkap. Tidak ada yang diproses.', 'ok');
                }
                return;
            }

            log(hasil.belum.length + ' data belum lengkap, ' + hasil.errors.length + ' data gagal dicek.', 'warn');

            if (!writeMode) {
                log('Mode Cek saja selesai. Tidak ada data yang ditulis.', 'ok');
                return;
            }

            var konfirmasi = 'Akan memproses ' + hasil.belum.length + ' data pada grid SIMKAH4.\n\n'
                + 'Wilayah: ' + config.provinsi + ' / ' + config.kabkota + ' / ' + config.kecamatan + '\n'
                + 'Mode: ' + (mode === 'lengkap' ? 'Isi + tandai selesai' : 'Isi jadwal') + '\n\n'
                + 'Lanjutkan?';

            if (!window.confirm(konfirmasi)) {
                log('Dibatalkan oleh pengguna.', 'warn');
                return;
            }

            var berhasil = 0;
            var gagal = [];

            for (var i = 0; i < hasil.belum.length; i++) {
                if (stopRequested) {
                    log('Dihentikan. Sisa data tidak diproses.', 'warn');
                    break;
                }

                var data = hasil.belum[i];
                progress('[' + (i + 1) + '/' + hasil.belum.length + '] ' + data.noDaftar);
                log('>> [No. ' + data.noUrut + '] ' + data.noDaftar + ' (' + data.belum.join(', ') + ')', 'info');

                try {
                    await prosesBimwin(data.noDaftar, data.belum, config, mode === 'lengkap');
                    berhasil++;
                    log('  OK ' + data.noDaftar, 'ok');
                } catch (e) {
                    gagal.push(data.noDaftar);
                    log('  GAGAL ' + data.noDaftar + ': ' + e.message, 'err');
                    tutupDialog();
                }

                await sleep(1000);
            }

            log('Selesai. Berhasil: ' + berhasil + ', gagal: ' + gagal.length + '.', gagal.length ? 'warn' : 'ok');
            if (gagal.length) log('Gagal: ' + gagal.join(', '), 'err');
        } catch (e) {
            log('ERROR: ' + e.message, 'err');
        } finally {
            if (notifOriginal && pageWindow.SimKah) pageWindow.SimKah.WindowNotif = notifOriginal;
            running = false;
            stopRequested = false;
            panelElement('bw-start').disabled = false;
            panelElement('bw-stop').disabled = true;
            progress('');
            refreshGrids();
        }
    }

    if (document.body) {
        initPanel();
    } else {
        window.addEventListener('DOMContentLoaded', initPanel);
    }
})();
