// Creation Lab: tick steps without a page reload, and ask before removing a project.
document.addEventListener('submit', async (ev) => {
  const f = ev.target;
  if (f.dataset.confirm && !confirm(f.dataset.confirm)) { ev.preventDefault(); return; }
  if (!f.action.endsWith('/step')) return;
  ev.preventDefault();
  const b = f.querySelector('.tick');
  const r = await fetch(f.action, { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!r.ok) { f.submit(); return; }
  const on = !b.classList.contains('on');
  b.classList.toggle('on', on); b.setAttribute('aria-pressed', on);
  const all = document.querySelectorAll('.steps .tick'), done = document.querySelectorAll('.steps .tick.on');
  const pct = Math.round(100 * done.length / all.length), ring = document.querySelector('.proj .ring');
  if (ring) { const a = ring.querySelector('.arc'), c = parseFloat(a.style.strokeDasharray); a.style.strokeDashoffset = (c * (1 - pct / 100)).toFixed(2); ring.querySelector('text').textContent = pct + '%'; }
});

// ---------- the Ad Lab composer: each platform's size, drawn from the campaign's artwork and the site's brand kit.
// The artwork is always cropped to fill (cover), never stretched; the logo keeps its own proportions.
(() => {
  const box = document.getElementById('composer'); if (!box) return;
  const k = JSON.parse(box.dataset.kit);
  if (!k.art) { box.innerHTML = ''; return; }
  const load = (src) => new Promise((ok) => { if (!src) return ok(null); const i = new Image(); i.onload = () => ok(i); i.onerror = () => ok(null); i.src = src; });
  const wrap = (g, text, maxW) => { const words = String(text || '').split(/\s+/); const lines = []; let cur = ''; for (const w of words) { const t = cur ? cur + ' ' + w : w; if (g.measureText(t).width > maxW && cur) { lines.push(cur); cur = w; } else cur = t; } if (cur) lines.push(cur); return lines; };
  const hexA = (h, a) => { const n = parseInt(h.slice(1), 16); return `rgba(${n >> 16 & 255},${n >> 8 & 255},${n & 255},${a})`; };
  function draw(cv, art, logo, s) {
    const g = cv.getContext('2d'), W = s.w, H = s.h; cv.width = W; cv.height = H;
    g.fillStyle = k.bg; g.fillRect(0, 0, W, H);
    // cover: scale to fill, crop the overflow evenly (never stretch)
    const sc = Math.max(W / art.width, H / art.height), dw = art.width * sc, dh = art.height * sc;
    g.drawImage(art, (W - dw) / 2, (H - dh) / 2, dw, dh);
    const tall = H / W > 1.2, u = Math.min(W, H) / 100;
    const grad = g.createLinearGradient(0, H * (tall ? 0.42 : 0.25), 0, H); grad.addColorStop(0, hexA(k.bg, 0)); grad.addColorStop(1, hexA(k.bg, 0.92));
    g.fillStyle = grad; g.fillRect(0, 0, W, H);
    if (logo) { const lw = Math.min(W * 0.16, 22 * u), lh = lw * (logo.naturalHeight || logo.height) / (logo.naturalWidth || logo.width || 1); g.drawImage(logo, 5 * u, 5 * u, lw, lh); }
    const pad = 6 * u, maxW = W - pad * 2; let y = H - pad;
    g.textBaseline = 'alphabetic';
    // bottom up: the site, the call to act, the coupon, the offer, then the headline above them
    g.font = `400 ${3.2 * u}px "Barlow Condensed", sans-serif`; g.fillStyle = hexA(k.text, 0.75); g.fillText((k.url || k.site).toUpperCase(), pad, y); y -= 5 * u;
    if (k.cta) { g.font = `500 ${4 * u}px "Barlow Condensed", sans-serif`; const t = k.cta.toUpperCase() + ' →'; const tw = g.measureText(t).width; g.fillStyle = k.accent; g.fillRect(pad, y - 5.6 * u, tw + 4 * u, 8 * u); g.fillStyle = k.bg; g.fillText(t, pad + 2 * u, y); y -= 11 * u; }
    if (k.coupon) { g.font = `500 ${4 * u}px "Barlow Condensed", sans-serif`; g.fillStyle = k.accent; g.fillText('CODE ' + k.coupon, pad, y); y -= 6.5 * u; }
    if (k.offer) { g.font = `400 ${4.6 * u}px "Cormorant Garamond", serif`; g.fillStyle = k.text; const ls = wrap(g, k.offer, maxW); for (const l of ls.slice(0, 2).reverse()) { g.fillText(l, pad, y); y -= 5.6 * u; } y -= 1.5 * u; }
    let fs = 9 * u; g.font = `500 ${fs}px "Cormorant SC", serif`; let ls = wrap(g, k.headline, maxW);
    while (ls.length > 3 && fs > 5 * u) { fs -= 0.5 * u; g.font = `500 ${fs}px "Cormorant SC", serif`; ls = wrap(g, k.headline, maxW); }
    g.fillStyle = k.text; for (const l of ls.slice(0, 3).reverse()) { g.fillText(l, pad, y); y -= fs * 1.08; }
  }
  (async () => {
    await Promise.all(['500 40px "Cormorant SC"', '400 40px "Cormorant Garamond"', '400 40px "Barlow Condensed"'].map((f) => document.fonts.load(f).catch(() => {})));
    const [art, logo] = await Promise.all([load(k.art), load(k.logo)]);
    if (!art) { box.innerHTML = '<p class="empty">The artwork couldn’t be opened.</p>'; return; }
    box.innerHTML = '<div class="sizes"></div>';
    for (const s of k.sizes) {
      const fig = document.createElement('figure'); fig.className = 'size';
      const cv = document.createElement('canvas'); draw(cv, art, logo, s);
      fig.append(cv);
      const cap = document.createElement('figcaption'); cap.innerHTML = `<b>${s.name}</b> <span class="home">${s.w} × ${s.h}</span>`;
      const dl = document.createElement('button'); dl.type = 'button'; dl.className = 'ghost'; dl.textContent = 'Download';
      dl.onclick = () => cv.toBlob((b) => { const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = `${k.site}-${s.name}`.replace(/[^\w-]+/g, '-') + '.png'; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 4000); }, 'image/png');
      const keep = document.createElement('button'); keep.type = 'button'; keep.className = 'ghost'; keep.textContent = 'Keep for posting';
      keep.onclick = () => cv.toBlob(async (b) => {
        const fd = new FormData(); fd.append('csrf', k.csrf); fd.append('p', s.p); fd.append('w', s.w); fd.append('png', b, 'art.png');
        keep.disabled = true; const r = await fetch(k.save, { method: 'POST', body: fd, credentials: 'same-origin' }).then((x) => x.json()).catch(() => ({}));
        keep.textContent = r.ok ? 'Kept ✓' : 'Didn’t save'; keep.disabled = false;
      }, 'image/png');
      cap.append(dl, keep); fig.append(cap); box.querySelector('.sizes').append(fig);
    }
  })();
})();

// copy buttons (words and tracked links)
document.addEventListener('click', (e) => {
  const b = e.target.closest('[data-copy]'); if (!b) return;
  navigator.clipboard.writeText(b.dataset.copy).then(() => { const t = b.textContent; b.textContent = 'Copied ✓'; setTimeout(() => (b.textContent = t), 1500); });
});
