<!-- ============================================================
     GLOBAL CUSTOM CONFIRM DIALOG
     Usage: showConfirmDialog('Message here', function() { /* on confirm */ });
     ============================================================ -->

<style>
  #customConfirmOverlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.50);
    z-index: 999999;
    justify-content: center;
    align-items: center;
  }
  #customConfirmOverlay.show {
    display: flex;
  }
  #customConfirmBox {
    background: #fff;
    border-radius: 16px;
    padding: 36px 32px 28px;
    max-width: 420px;
    width: 90%;
    text-align: center;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.22);
    animation: confirmPopIn 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
  }
  @keyframes confirmPopIn {
    from { transform: scale(0.80); opacity: 0; }
    to   { transform: scale(1);    opacity: 1; }
  }
  #customConfirmBox .confirm-icon {
    font-size: 2.8rem;
    margin-bottom: 14px;
    line-height: 1;
  }
  #customConfirmBox .confirm-icon i {
    color: #f59e0b;
  }
  #customConfirmBox .confirm-icon i.danger-icon {
    color: #ef4444;
  }
  #customConfirmBox .confirm-icon i.success-icon {
    color: #22c55e;
  }
  #customConfirmBox .confirm-icon i.info-icon {
    color: #0ea5e9;
  }
  #customConfirmBox h3 {
    margin: 0 0 10px;
    color: #1e293b;
    font-size: 1.15rem;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
  }
  #customConfirmBox p {
    color: #64748b;
    font-size: 0.93rem;
    margin-bottom: 26px;
    line-height: 1.55;
    font-family: 'Poppins', sans-serif;
  }
  .confirm-btn-row {
    display: flex;
    gap: 12px;
    justify-content: center;
  }
  #confirmCancelBtn {
    background: #f1f5f9;
    color: #475569;
    border: none;
    padding: 10px 28px;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    transition: background 0.18s;
  }
  #confirmCancelBtn:hover { background: #e2e8f0; }
  #confirmOkBtn {
    background: #0ea5e9;
    color: #fff;
    border: none;
    padding: 10px 28px;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    transition: background 0.18s;
  }
  #confirmOkBtn:hover { background: #0284c7; }
  #confirmOkBtn.danger {
    background: #ef4444;
  }
  #confirmOkBtn.danger:hover { background: #dc2626; }
</style>

<div id="customConfirmOverlay">
  <div id="customConfirmBox">
    <div class="confirm-icon" id="confirmIcon">
      <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <h3 id="confirmTitle">Confirm Action</h3>
    <p id="confirmMessage">Are you sure?</p>
    <div class="confirm-btn-row">
      <button id="confirmCancelBtn" onclick="closeConfirmDialog()">Cancel</button>
      <button id="confirmOkBtn" onclick="_confirmCallback()">OK</button>
    </div>
  </div>
</div>

<script>
  let _confirmCallback = function() {};

  // Icon map: keyword -> Font Awesome HTML
  const _confirmIconMap = {
    'warning':  '<i class="fa-solid fa-triangle-exclamation"></i>',
    'danger':   '<i class="fa-solid fa-circle-exclamation danger-icon"></i>',
    'delete':   '<i class="fa-solid fa-trash danger-icon"></i>',
    'restore':  '<i class="fa-solid fa-rotate-left success-icon"></i>',
    'logout':   '<i class="fa-solid fa-right-from-bracket info-icon"></i>',
    'approve':  '<i class="fa-solid fa-circle-check success-icon"></i>',
    'reject':   '<i class="fa-solid fa-circle-xmark danger-icon"></i>',
    'refund':   '<i class="fa-solid fa-money-bill-wave info-icon"></i>',
    'deactivate': '<i class="fa-solid fa-ban danger-icon"></i>',
    'reset':    '<i class="fa-solid fa-triangle-exclamation danger-icon"></i>',
    'clear':    '<i class="fa-solid fa-eraser danger-icon"></i>',
  };

  /**
   * Show the global custom confirm dialog.
   *
   * @param {string}   message   - The confirmation message to display.
   * @param {function} onConfirm - Callback executed when user clicks OK.
   * @param {object}   [options] - Optional settings.
   * @param {string}   [options.title]   - Dialog title. Default: "Confirm Action"
   * @param {string}   [options.icon]    - Icon keyword (logout, delete, restore, approve, etc). Default: "warning"
   * @param {boolean}  [options.danger]  - Red OK button for destructive actions. Default: false
   * @param {string}   [options.okText]  - OK button label. Default: "OK"
   */
  function showConfirmDialog(message, onConfirm, options) {
    options = options || {};
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmTitle').textContent   = options.title  || 'Confirm Action';
    document.getElementById('confirmOkBtn').textContent   = options.okText || 'OK';

    // Set icon
    const iconKey = (options.icon || 'warning').toLowerCase();
    const iconHtml = _confirmIconMap[iconKey] || _confirmIconMap['warning'];
    document.getElementById('confirmIcon').innerHTML = iconHtml;

    const okBtn = document.getElementById('confirmOkBtn');
    if (options.danger) {
      okBtn.classList.add('danger');
    } else {
      okBtn.classList.remove('danger');
    }

    _confirmCallback = function() {
      closeConfirmDialog();
      if (typeof onConfirm === 'function') onConfirm();
    };

    document.getElementById('customConfirmOverlay').classList.add('show');
  }

  function closeConfirmDialog() {
    document.getElementById('customConfirmOverlay').classList.remove('show');
  }

  // Close when clicking outside the box
  document.getElementById('customConfirmOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirmDialog();
  });

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfirmDialog();
  });
</script>
