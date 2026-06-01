/* ============================================================
   TBI-MCE Task Manager — Main JavaScript
   ============================================================ */

'use strict';

// ── Dark Mode ─────────────────────────────────────────────────
(function initDarkMode() {
  const stored = localStorage.getItem('tbi_theme') || 'light';
  applyTheme(stored);

  document.querySelectorAll('#darkToggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-bs-theme');
      const next    = current === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      localStorage.setItem('tbi_theme', next);
    });
  });

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.querySelectorAll('#darkToggle i').forEach(i => {
      i.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    });
  }
})();

// ── Notifications (poll every 60 s) ──────────────────────────
(function initNotifications() {
  const bell      = document.getElementById('notifBell');
  const list      = document.getElementById('notifList');
  const badge     = document.querySelector('.notif-count');
  if (!bell || !list || !badge) return;

  function loadNotifs() {
    const base = document.querySelector('base')?.href || '';
    fetch(getBaseUrl() + '/api/notifications_api.php?action=unread')
      .then(r => r.json())
      .then(data => {
        const count = data.count || 0;
        if (count > 0) {
          badge.textContent = count;
          badge.classList.remove('d-none');
        } else {
          badge.classList.add('d-none');
        }

        if (data.notifications && data.notifications.length > 0) {
          list.innerHTML = data.notifications.map(n => `
            <li>
              <a class="dropdown-item small py-2 ${n.read_status === 'unread' ? 'fw-600' : ''}"
                 href="#" onclick="markNotifRead('${n.id}', this)">
                <div>${n.message}</div>
                <div class="text-muted" style="font-size:.7rem">${n.created_at}</div>
              </a>
            </li>
          `).join('<li><hr class="dropdown-divider my-1"></li>');
        } else {
          list.innerHTML = '<li><div class="dropdown-item-text text-center text-muted small py-2">No new notifications</div></li>';
        }
      })
      .catch(() => {});
  }

  loadNotifs();
  setInterval(loadNotifs, 60000);
})();

function markNotifRead(id, el) {
  fetch(getBaseUrl() + '/api/notifications_api.php?action=mark_read&id=' + id)
    .then(() => {
      el.closest('li').classList.remove('fw-600');
    });
  return false;
}

// ── Export Table to Excel (client-side SheetJS) ───────────────
function exportTableExcel(tableId, filename) {
  const table = document.getElementById(tableId);
  if (!table) { alert('Table not found'); return; }

  const wb   = XLSX.utils.table_to_book(table, { sheet: 'Report' });
  const date = new Date().toISOString().split('T')[0];
  XLSX.writeFile(wb, filename + '_' + date + '.xlsx');
}

// ── Export Table to PDF (client-side jsPDF) ───────────────────
function exportTablePDF(tableId, title) {
  const table = document.getElementById(tableId);
  if (!table || typeof jspdf === 'undefined') { alert('PDF library not loaded'); return; }

  const { jsPDF } = jspdf;
  const doc  = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

  // Header
  doc.setFontSize(14);
  doc.setTextColor(13, 43, 92);
  doc.text('TBI – MCE Hassan', 15, 15);
  doc.setFontSize(11);
  doc.text(title, 15, 22);
  doc.setFontSize(8);
  doc.setTextColor(100);
  doc.text('Generated: ' + new Date().toLocaleString('en-IN'), 15, 28);

  // Table
  doc.autoTable({
    html:       '#' + tableId,
    startY:     32,
    styles:     { fontSize: 8, cellPadding: 2 },
    headStyles: { fillColor: [13, 43, 92], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [240, 244, 255] },
  });

  const date = new Date().toISOString().split('T')[0];
  doc.save(title.replace(/\s+/g,'_') + '_' + date + '.pdf');
}

// ── Confirm Delete ────────────────────────────────────────────
function confirmDelete(url) {
  if (confirm('Delete this record? This cannot be undone.')) {
    window.location.href = url;
  }
}

// ── Auto-dismiss alerts after 5 s ────────────────────────────
document.querySelectorAll('.alert-dismissible').forEach(el => {
  setTimeout(() => {
    const btn = el.querySelector('.btn-close');
    if (btn) btn.click();
  }, 5000);
});

// ── Tooltip init ──────────────────────────────────────────────
document.querySelectorAll('[title]').forEach(el => {
  new bootstrap.Tooltip(el, { trigger: 'hover', placement: 'top' });
});

// ── Helper: get base URL from meta tag or fallback ───────────
function getBaseUrl() {
  const meta = document.querySelector('meta[name="base-url"]');
  if (meta) return meta.content;
  // fallback: strip filename from current path
  const p = window.location.pathname;
  const parts = p.split('/');
  parts.pop(); parts.pop(); // remove file and one dir
  return window.location.origin + parts.join('/');
}
