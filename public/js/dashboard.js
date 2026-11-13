/* NilaCare dashboard: ambil data tiap 5 detik, grafik tiap 30 detik, kirim perintah manual. */
(() => {
  const app = document.getElementById('app');
  if (!app) return;
  const url = app.dataset;
  const TH = JSON.parse(url.thresholds || '{}');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const $ = (sel, el = document) => el.querySelector(sel);
  const $$ = (sel, el = document) => [...el.querySelectorAll(sel)];
  let hours = 6;
  const charts = {};

  const zoneText = { optimal: 'Optimal', waspada: 'Waspada', bahaya: 'Bahaya' };
  const fmt = (v, d = 1) => (v === null || v === undefined ? '–' : Number(v).toFixed(d).replace('.', ','));

  function toast(msg, isError = false) {
    const t = $('#toast');
    t.textContent = msg;
    t.className = 'toast show' + (isError ? ' err' : '');
    clearTimeout(t._h);
    t._h = setTimeout(() => (t.className = 'toast'), 3500);
  }

  async function post(target, body) {
    try {
      const res = await fetch(target, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json().catch(() => ({}));
      toast(data.message || (res.ok ? 'Terkirim' : 'Gagal'), !res.ok);
      setTimeout(loadLive, 1500);
    } catch (e) {
      toast('Tidak dapat menghubungi server', true);
    }
  }

  function setZone(el, zone) {
    el.textContent = zoneText[zone] || '–';
    el.className = 'zone ' + (zone || '');
  }

  function render(d) {
    const p = d.pond;
    const online = $('#online');
    online.textContent = p.online ? 'Online' : 'Offline';
    online.className = 'badge ' + (p.online ? 'ok' : 'off');
    const mode = $('#mode');
    mode.textContent = p.mode === 'manual' ? 'Mode manual' : 'Mode otomatis';
    mode.className = 'badge ' + (p.mode === 'manual' ? 'warn' : '');

    const r = d.reading;
    $('#age').textContent = r ? `data terakhir ${r.received_at} (${r.age})` : 'belum ada data dari perangkat';
    if (r) {
      const vals = { do: [r.do, 1], temp: [r.temp, 1], ph: [r.ph, 1], ntu: [r.ntu, 0], level: [r.level, 0] };
      for (const [k, [v, dec]] of Object.entries(vals)) {
        const card = $(`[data-metric="${k}"]`);
        $('.v', card).textContent = fmt(v, dec);
        if (k !== 'level') {
          setZone($('.zone', card), r.zone[k]);
          card.dataset.zone = r.zone[k] || '';
        }
      }
      $('#temp-tank').textContent = fmt(r.temp_tank, 1);
      const fl = $('#float');
      fl.textContent = r.float_min ? 'Level minimum!' : 'Aman';
      fl.className = 'zone ' + (r.float_min ? 'bahaya' : 'optimal');
      $('[data-metric="level"]').dataset.zone = r.float_min ? 'bahaya' : 'optimal';

      for (const row of $$('.act-row')) {
        const on = !!(r.actuators || {})[row.dataset.act];
        const pill = $('.pill', row);
        pill.textContent = on ? 'ON' : 'OFF';
        pill.className = 'pill ' + (on ? 'on' : '');
      }
      const wc = $('#wc');
      const active = r.water_change && r.water_change !== 'idle';
      wc.classList.toggle('hidden', !active);
      wc.textContent = active ? 'Ganti air: ' + r.water_change : '';
      wc.className = 'badge info' + (active ? '' : ' hidden');
    }

    const al = $('#alarm');
    al.classList.toggle('hidden', !d.alarm);
    if (d.alarm) {
      $('#alarm-text').textContent = d.alarm.message;
      $('#alarm-since').textContent = `sejak ${d.alarm.since}` + (d.alarm.acknowledged ? ' · sudah dikonfirmasi' : '');
      $('#ack-btn').disabled = d.alarm.acknowledged;
    }

    const ev = $('#events');
    ev.innerHTML = d.events.length
      ? d.events.map((e) => `<li><span class="t">${e.time}</span><span class="k k-${e.kind}">${e.text}</span></li>`).join('')
      : '<li class="muted">Belum ada kejadian.</li>';
    const cm = $('#commands');
    const st = { sent: ['menunggu', 'warn'], confirmed: ['terkonfirmasi', 'ok'], failed: ['gagal', 'off'] };
    cm.innerHTML = d.commands.length
      ? d.commands.map((c) => `<li><span class="t">${c.time}</span><span>${c.summary}${c.user ? ` <span class="muted">· ${c.user}</span>` : ''}</span><span class="badge ${st[c.status][1]}" title="${c.error || ''}">${st[c.status][0]}</span></li>`).join('')
      : '<li class="muted">Belum ada perintah.</li>';
  }

  async function loadLive() {
    try {
      const res = await fetch(url.live, { headers: { Accept: 'application/json' } });
      if (res.status === 401 || res.redirected) return location.reload();
      render(await res.json());
    } catch (e) {
      $('#online').textContent = 'Server tidak terjangkau';
    }
  }

  function css(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  }

  function makeChart(id, key, lines) {
    if (typeof Chart === 'undefined') return;
    const ctx = document.getElementById(id);
    charts[key] = new Chart(ctx, {
      type: 'line',
      data: {
        labels: [],
        datasets: [
          { data: [], borderColor: css('--accent'), backgroundColor: css('--accent-soft'), fill: true, borderWidth: 2, pointRadius: 0, tension: 0.25 },
          ...lines.map(([label, value, color]) => ({ label, data: [], borderColor: css(color), borderDash: [5, 4], borderWidth: 1.2, pointRadius: 0, fill: false, _value: value })),
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: false }, tooltip: { filter: (i) => i.datasetIndex === 0 } },
        scales: {
          x: { ticks: { maxTicksLimit: 6, color: css('--muted') }, grid: { display: false } },
          y: { ticks: { color: css('--muted') }, grid: { color: css('--grid') } },
        },
      },
    });
  }

  async function loadHistory() {
    try {
      const res = await fetch(`${url.history}?hours=${hours}`, { headers: { Accept: 'application/json' } });
      const h = await res.json();
      for (const key of ['do', 'temp', 'ph', 'ntu']) {
        const c = charts[key];
        if (!c) continue;
        c.data.labels = h.labels;
        c.data.datasets[0].data = h[key];
        c.data.datasets.slice(1).forEach((ds) => (ds.data = h.labels.map(() => ds._value)));
        c.update();
      }
    } catch (e) { /* abaikan, dicoba lagi */ }
  }

  makeChart('c-do', 'do', [['Aerator A ON', TH.doAOn, '--warn'], ['Kritis', TH.doCrit, '--danger']]);
  makeChart('c-temp', 'temp', [['Paranet tutup', TH.tempShadeClose, '--warn'], ['Heater ON', TH.tempHeaterOn, '--info'], ['Batas atas', TH.tempMax, '--danger'], ['Batas bawah', TH.tempMin, '--danger']]);
  makeChart('c-ph', 'ph', [['Dosis kapur', TH.phLow, '--warn'], ['Batas atas', TH.phHigh, '--danger']]);
  makeChart('c-ntu', 'ntu', [['Ganti air', TH.ntuHigh, '--danger'], ['Bersih', TH.ntuClean, '--ok']]);

  $$('.act-row').forEach((row) =>
    $$('.seg button', row).forEach((b) => b.addEventListener('click', () => post(url.actuator, { actuator: row.dataset.act, state: b.dataset.set })))
  );
  $$('[data-mode-set]').forEach((b) => b.addEventListener('click', () => post(url.mode, { mode: b.dataset.modeSet })));
  $$('[data-action-run]').forEach((b) =>
    b.addEventListener('click', () => {
      if (b.dataset.actionRun === 'water_change' && !confirm('Mulai ganti air sebagian sekarang?')) return;
      post(url.action, { action: b.dataset.actionRun });
    })
  );
  $$('#range button').forEach((b) =>
    b.addEventListener('click', () => {
      $$('#range button').forEach((x) => x.classList.toggle('on', x === b));
      hours = Number(b.dataset.hours);
      loadHistory();
    })
  );

  loadLive();
  loadHistory();
  setInterval(loadLive, 5000);
  setInterval(loadHistory, 30000);
})();
