// Enable Bootstrap tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>{
  new bootstrap.Tooltip(el);
});

// ----- Recent Orders filter/search -----
(function(){
  const search   = document.getElementById('orderSearch');
  const status   = document.getElementById('orderStatusFilter');
  const rows     = Array.from(document.querySelectorAll('.recent-orders tbody tr'));
  const hasRows  = rows.some(tr => !tr.querySelector('td[colspan]'));

  if (search) search.disabled = !hasRows;
  if (status) status.disabled = !hasRows;

  function apply(){
    const q = (search?.value || '').trim().toLowerCase();
    const s = (status?.value || '').trim().toLowerCase();
    rows.forEach(tr=>{
      if (tr.querySelector('td[colspan]')) return; // skip "No orders yet."
      const text = tr.innerText.toLowerCase();
      const badge = tr.querySelector('.badge')?.innerText.toLowerCase() || '';
      const okQ = !q || text.includes(q);
      const okS = !s || badge === s;
      tr.style.display = (okQ && okS) ? '' : 'none';
    });
  }
  search && search.addEventListener('input', apply);
  status && status.addEventListener('change', apply);
})();

// ----- Top Items search -----
(function(){
  const search = document.getElementById('itemSearch');
  const rows   = Array.from(document.querySelectorAll('.top-items tbody tr'));
  const hasRows = rows.some(tr => !tr.querySelector('td[colspan]'));
  if (search) search.disabled = !hasRows;

  function apply(){
    const q = (search?.value || '').trim().toLowerCase();
    rows.forEach(tr=>{
      if (tr.querySelector('td[colspan]')) return; // skip "No sales yet."
      const t = tr.innerText.toLowerCase();
      tr.style.display = !q || t.includes(q) ? '' : 'none';
    });
  }
  search && search.addEventListener('input', apply);
})();
