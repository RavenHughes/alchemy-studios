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
