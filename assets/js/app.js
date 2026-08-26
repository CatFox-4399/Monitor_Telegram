// ============================================================
// assets/js/app.js
// WebMonitor — Admin panel JavaScript
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

  // ── Password show/hide toggle ──────────────────────────────
  document.querySelectorAll('.toggle-password').forEach(function (icon) {
    icon.addEventListener('click', function () {
      var input = document.querySelector(this.dataset.target);
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        this.textContent = '🙈';
      } else {
        input.type = 'password';
        this.textContent = '👁️';
      }
    });
  });

  // ── Confirm delete modal ───────────────────────────────────
  var deleteModal   = document.getElementById('deleteModal');
  var deleteForm    = document.getElementById('deleteForm');
  var deleteSiteName = document.getElementById('deleteSiteName');

  document.querySelectorAll('.btn-delete-site').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id   = this.dataset.id;
      var name = this.dataset.name;
      if (deleteForm)    deleteForm.action = 'websites.php?action=delete&id=' + id;
      if (deleteSiteName) deleteSiteName.textContent = name;
      if (deleteModal)   deleteModal.classList.add('open');
    });
  });

  document.getElementById('cancelDelete')?.addEventListener('click', function () {
    deleteModal.classList.remove('open');
  });

  deleteModal?.addEventListener('click', function (e) {
    if (e.target === deleteModal) deleteModal.classList.remove('open');
  });

  // ── Auto-dismiss flash alerts ─────────────────────────────
  var alerts = document.querySelectorAll('.alert');
  alerts.forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = 'opacity .5s';
      alert.style.opacity = '0';
      setTimeout(function () { alert.remove(); }, 500);
    }, 4000);
  });

  // ── Inline edit toggle for website rows ───────────────────
  document.querySelectorAll('.btn-edit-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var rowId   = this.dataset.id;
      var viewRow = document.getElementById('view-row-' + rowId);
      var editRow = document.getElementById('edit-row-' + rowId);
      if (!viewRow || !editRow) return;
      var isEditing = editRow.style.display !== 'none' && editRow.style.display !== '';
      if (isEditing) {
        editRow.style.display = 'none';
        viewRow.style.display = '';
        this.textContent = '✏️ Edit';
      } else {
        editRow.style.display = '';
        viewRow.style.display = 'none';
        this.textContent = '✖ Cancel';
      }
    });
  });

  // ── Filter form: submit on select change ──────────────────
  document.querySelectorAll('.auto-submit-select').forEach(function (sel) {
    sel.addEventListener('change', function () {
      this.closest('form').submit();
    });
  });

  // ── Real-time search debounce ─────────────────────────────
  var searchInput = document.getElementById('searchInput');
  if (searchInput) {
    var debounceTimer;
    searchInput.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        searchInput.closest('form').submit();
      }, 400);
    });
  }

  // ── Confirm run-now button ────────────────────────────────
  document.getElementById('btnRunNow')?.addEventListener('click', function (e) {
    if (!confirm('Run monitoring check now for all due websites?')) {
      e.preventDefault();
    }
  });

  // ── Settings form: live preview of Telegram token ─────────
  var tokenInput = document.getElementById('telegram_bot_token');
  var tokenPreview = document.getElementById('tokenPreview');
  if (tokenInput && tokenPreview) {
    tokenInput.addEventListener('input', function () {
      var val = this.value.trim();
      tokenPreview.textContent = val.length > 10
        ? val.substring(0, 10) + '...'
        : (val || '(not set)');
    });
  }

  // ── Tooltips (simple title-based) ─────────────────────────
  document.querySelectorAll('[data-tooltip]').forEach(function (el) {
    el.setAttribute('title', el.dataset.tooltip);
  });
});
