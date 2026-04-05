/**
 * Instalegl — Core Functions
 * OTP is handled server-side via Brevo (backend/send_otp.php, verify_otp.php).
 * Payment is now done via QR code + screenshot upload (no Razorpay).
 */

(function () {
  'use strict';

  /* ═══════════════════════════════════════════════════════
     Inject QR Payment Modal CSS once
  ════════════════════════════════════════════════════════ */
  var _styleInjected = false;
  function _injectStyles() {
    if (_styleInjected) return;
    _styleInjected = true;
    var style = document.createElement('style');
    style.textContent = [
      '#il-qr-overlay{display:none;position:fixed;inset:0;z-index:99999;background:rgba(5,8,20,.85);',
      'backdrop-filter:blur(12px);align-items:center;justify-content:center;padding:20px;}',
      '#il-qr-overlay.open{display:flex;}',
      '#il-qr-box{background:#111827;border:1px solid rgba(255,255,255,.1);border-radius:18px;',
      'padding:32px 28px;max-width:440px;width:100%;box-shadow:0 40px 80px rgba(0,0,0,.6);',
      'font-family:"Plus Jakarta Sans",sans-serif;color:#f4f1ec;position:relative;',
      'animation:ilQrIn .3s cubic-bezier(.34,1.56,.64,1);}',
      '@keyframes ilQrIn{from{transform:scale(.88) translateY(20px);opacity:0}to{transform:none;opacity:1}}',
      '#il-qr-close{position:absolute;top:14px;right:14px;background:rgba(255,255,255,.07);',
      'border:1px solid rgba(255,255,255,.12);border-radius:50%;width:30px;height:30px;',
      'display:flex;align-items:center;justify-content:center;cursor:pointer;',
      'font-size:14px;color:rgba(255,255,255,.6);transition:all .2s;}',
      '#il-qr-close:hover{background:rgba(255,255,255,.14);color:#fff;}',
      '.il-pay-title{font-size:18px;font-weight:700;color:#fff;margin-bottom:4px;margin-right:24px;}',
      '.il-pay-sub{font-size:13px;color:#9ba5c0;margin-bottom:20px;}',
      '.il-pay-amount{display:inline-flex;align-items:center;gap:6px;',
      'background:rgba(4,108,78,.12);border:1px solid rgba(4,108,78,.3);',
      'border-radius:100px;padding:6px 16px;font-size:15px;font-weight:700;',
      'color:#10B981;margin-bottom:20px;}',
      '.il-qr-img-wrap{background:#fff;border-radius:12px;padding:12px;',
      'display:flex;align-items:center;justify-content:center;margin-bottom:14px;}',
      '.il-qr-img-wrap img{width:180px;height:180px;object-fit:contain;display:block;}',
      '.il-upi-row{text-align:center;font-size:12px;color:#9ba5c0;margin-bottom:20px;}',
      '.il-upi-id{font-weight:700;color:#10B981;font-size:14px;letter-spacing:.02em;}',
      '.il-upload-label{font-size:11px;font-weight:700;color:#9ba5c0;letter-spacing:.06em;',
      'text-transform:uppercase;margin-bottom:8px;display:block;}',
      '.il-upload-zone{border:2px dashed rgba(255,255,255,.14);border-radius:12px;',
      'padding:20px;text-align:center;cursor:pointer;transition:all .25s;position:relative;',
      'background:rgba(255,255,255,.02);margin-bottom:16px;}',
      '.il-upload-zone:hover,.il-upload-zone.il-over{border-color:#046C4E;background:rgba(4,108,78,.05);}',
      '.il-upload-zone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}',
      '.il-upload-icon{font-size:28px;margin-bottom:6px;}',
      '.il-upload-txt{font-size:13px;font-weight:600;color:#fff;margin-bottom:3px;}',
      '.il-upload-hint{font-size:11px;color:#9ba5c0;}',
      '.il-preview-bar{display:none;align-items:center;gap:10px;',
      'background:rgba(4,108,78,.07);border:1px solid rgba(4,108,78,.2);',
      'border-radius:10px;padding:10px 14px;margin-bottom:16px;}',
      '.il-preview-bar.show{display:flex;}',
      '.il-preview-thumb{width:44px;height:44px;border-radius:7px;',
      'overflow:hidden;background:rgba(255,255,255,.05);',
      'display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}',
      '.il-preview-thumb img{width:100%;height:100%;object-fit:cover;}',
      '.il-preview-name{font-size:12px;font-weight:700;color:#fff;',
      'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;}',
      '.il-preview-size{font-size:11px;color:#10B981;}',
      '.il-preview-rm{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);',
      'color:#f87171;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;',
      'cursor:pointer;flex-shrink:0;font-family:inherit;}',
      '#il-pay-submit{width:100%;background:linear-gradient(135deg,#046C4E,#059669);',
      'color:#fff;border:none;border-radius:12px;padding:13px 24px;font-size:15px;',
      'font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;',
      'display:flex;align-items:center;justify-content:center;gap:8px;}',
      '#il-pay-submit:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(4,108,78,.4);}',
      '#il-pay-submit:disabled{opacity:.6;transform:none;cursor:not-allowed;}',
      '#il-pay-err{font-size:13px;color:#f87171;font-weight:600;',
      'margin-top:10px;display:none;text-align:center;}',
      '.il-pay-note{font-size:11px;color:rgba(155,165,192,.6);text-align:center;margin-top:12px;line-height:1.65;}',
    ].join('');
    document.head.appendChild(style);
  }

  /* ═══════════════════════════════════════════════════════
     PUBLIC: ilSendOTP -> creates OTP requests and reuses resend_otp.php
  ════════════════════════════════════════════════════════ */
  function _ilDigits(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function _getOtpContext(phone) {
    var context = {
      name: '',
      email: '',
      service: '',
      phone: phone
    };

    // Auto-detect global form data across different pages
    if (typeof window._ilData !== 'undefined' && window._ilData && window._ilData.name) {
      context.name = window._ilData.name;
      context.email = window._ilData.email || '';
      context.service = window.IL_SERVICE_NAME || 'Legal Service';
      context.phone = window._ilData.mobile || phone;
      return context;
    }

    if (typeof window._ilHomeData !== 'undefined' && window._ilHomeData && window._ilHomeData.name) {
      context.name = window._ilHomeData.name;
      context.email = window._ilHomeData.email || '';
      context.service = window._ilHomeData.service || 'Legal Service';
      context.phone = window._ilHomeData.mobile || phone;
    }

    return context;
  }

  function _parseJsonResponse(response) {
    return response.text().then(function (text) {
      var data;

      try {
        data = JSON.parse(text);
      } catch (err) {
        var snippet = String(text || '').replace(/\s+/g, ' ').slice(0, 120);
        var parseError = new Error(
          snippet
            ? 'Server returned an invalid response. ' + snippet
            : 'Server returned an invalid response.'
        );
        parseError.code = 'invalid_json';
        parseError.status = response.status;
        parseError.responseText = text;
        throw parseError;
      }

      if (!response.ok) {
        throw new Error(data.message || ('Request failed with status ' + response.status + '.'));
      }

      return data;
    });
  }

  function _postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(_parseJsonResponse);
  }

  function _sendOtpRequest(url, payload, fallbackUrl) {
    return _postJson(url, payload)
      .catch(function (err) {
        var canFallback = !!fallbackUrl && (err.code === 'invalid_json' || err.status === 404 || err.status === 500);
        if (!canFallback) throw err;
        return _postJson(fallbackUrl, payload);
      })
      .then(function (d) {
        if (!d.success) throw new Error(d.message || 'Failed to send OTP.');
        return d;
      });
  }

  window.ilSendOTP = function (phone, containerId) {
    var context = _getOtpContext(phone);
    var lastOtpState = window._ilOtpState || null;
    var sameRequest = lastOtpState && _ilDigits(lastOtpState.phone) === _ilDigits(context.phone);

    // Once a booking OTP has been created for this phone, subsequent calls should
    // reuse the resend endpoint instead of creating duplicate bookings.
    if (sameRequest) {
      return _sendOtpRequest('backend/resend_otp.php', {
        phone: context.phone,
        name: context.name || 'there'
      })
        .then(function (d) {
          window._ilMemPhone = context.phone;
          return d;
        });
    }

    return _sendOtpRequest('backend/send_otp.php', {
      name: context.name,
      phone: context.phone,
      email: context.email,
      service: context.service,
      require_otp: true
    }, 'backend/submit_booking.php')
      .then(function (d) {
        window._ilMemPhone = context.phone;
        window._ilOtpState = {
          phone: context.phone,
          email: context.email,
          name: context.name,
          service: context.service
        };
        return d;
      });
  };

  /* ═══════════════════════════════════════════════════════
     PUBLIC: ilVerifyOTP -> routes to backend/verify_otp.php
  ════════════════════════════════════════════════════════ */
  window.ilVerifyOTP = function (otp) {
    var phone = window._ilMemPhone || '';
    if (!phone) {
      if (typeof window._ilData !== 'undefined') phone = window._ilData.mobile || phone;
      else if (typeof window._ilHomeData !== 'undefined') phone = window._ilHomeData.mobile || phone;
    }
    return fetch('backend/verify_otp.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: phone, otp: otp, type: 'booking' })
    })
      .then(_parseJsonResponse)
      .then(function (d) {
        if (!d.success) throw new Error(d.message || 'Invalid OTP.');
        window._ilMemRef = d.ref; // capture the real db ref string
        window._ilOtpState = null;
        return d;
      });
  };

  /* ═══════════════════════════════════════════════════════
     PUBLIC: ilLaunchPayment({ name, phone, service, ref,
                               amount, onSuccess, onFailure })
     amount = 0  → skip payment, go straight to success.
     Displays a QR code modal; on screenshot upload → POST to
     backend/submit_payment.php; calls onSuccess(txnId, ref).
  ════════════════════════════════════════════════════════ */
  window.ilLaunchPayment = function (opts) {
    var ref = opts.ref || window._ilMemRef || _genRef();

    /* Free / consultation — skip payment */
    if (!opts.amount || parseInt(opts.amount) === 0) {
      if (typeof opts.onSuccess === 'function') {
        opts.onSuccess('FREE_' + ref, ref);
      }
      return;
    }

    _injectStyles();
    _openQRModal(opts, ref);
  };

  /* ── Build and open the QR modal ───────────────────────── */
  function _openQRModal(opts, ref) {
    // Remove if already exists
    var existing = document.getElementById('il-qr-overlay');
    if (existing) existing.remove();

    var upiId = (typeof IL_UPI_ID !== 'undefined' ? IL_UPI_ID : 'instalegl@upi');
    var siteName = (typeof IL_SITE_NAME !== 'undefined' ? IL_SITE_NAME : 'Instalegl');
    var qrImage = (typeof IL_QR_IMAGE !== 'undefined' ? IL_QR_IMAGE : null);
    var amount = opts.amount || 0;

    /* Build QR URL or use static image */
    var qrSrc;
    if (qrImage) {
      qrSrc = qrImage;
    } else {
      var qrData = encodeURIComponent('upi://pay?pa=' + upiId + '&pn=' + encodeURIComponent(siteName) + '&am=' + amount + '&cu=INR&tn=' + encodeURIComponent((opts.service || 'Legal Service')));
      qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?data=' + qrData + '&size=200x200&margin=0';
    }

    var overlay = document.createElement('div');
    overlay.id = 'il-qr-overlay';
    overlay.innerHTML = [
      '<div id="il-qr-box" role="dialog" aria-modal="true" aria-label="Payment QR Code">',
      '<button id="il-qr-close" aria-label="Close payment dialog">✕</button>',
      '<div class="il-pay-title">Scan &amp; Pay to Confirm</div>',
      '<div class="il-pay-sub">Scan the QR code below with any UPI app (GPay, PhonePe, Paytm, etc.)</div>',
      '<div class="il-pay-amount">',
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">',
      '<path d="M6 3h12M6 8h12M8 21l4-7 4 7M4 8h16"/>',
      '</svg>',
      '₹' + amount,
      '</div>',
      '<div class="il-qr-img-wrap">',
      '<img src="' + qrSrc + '" alt="UPI QR Code for ₹' + amount + '" id="il-qr-img">',
      '</div>',
      '<div class="il-upi-row">',
      'UPI ID: <span class="il-upi-id">' + upiId + '</span>',
      '</div>',
      '<label class="il-upload-label">Upload Payment Screenshot *</label>',
      '<div class="il-upload-zone" id="il-uzone">',
      '<input type="file" id="il-pay-file" accept="image/*,.pdf">',
      '<div class="il-upload-icon">📸</div>',
      '<div class="il-upload-txt">Tap to upload screenshot</div>',
      '<div class="il-upload-hint">JPG / PNG / PDF · Max 5 MB</div>',
      '</div>',
      '<div class="il-preview-bar" id="il-prev">',
      '<div class="il-preview-thumb" id="il-prev-thumb">🖼</div>',
      '<div style="flex:1;min-width:0">',
      '<div class="il-preview-name" id="il-prev-name">—</div>',
      '<div class="il-preview-size" id="il-prev-size">—</div>',
      '</div>',
      '<button class="il-preview-rm" id="il-prev-rm">Remove</button>',
      '</div>',
      '<button id="il-pay-submit" disabled>',
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">',
      '<path d="M12 2L3 7v5c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V7L12 2z"/>',
      '<path d="M9 12l2 2 4-4"/>',
      '</svg>',
      'Confirm Payment',
      '</button>',
      '<div id="il-pay-err"></div>',
      '<div class="il-pay-note">',
      '⚠ Upload the screenshot immediately after payment. Booking is confirmed only after our team verifies receipt.',
      '</div>',
      '</div>',
    ].join('');

    document.body.appendChild(overlay);

    /* Open with animation */
    requestAnimationFrame(function () {
      overlay.classList.add('open');
    });

    /* Wire events */
    var fileInput = document.getElementById('il-pay-file');
    var submitBtn = document.getElementById('il-pay-submit');
    var errEl = document.getElementById('il-pay-err');
    var prevBar = document.getElementById('il-prev');
    var prevThumb = document.getElementById('il-prev-thumb');
    var prevName = document.getElementById('il-prev-name');
    var prevSize = document.getElementById('il-prev-size');
    var prevRm = document.getElementById('il-prev-rm');
    var uzone = document.getElementById('il-uzone');
    var selectedFile = null;

    function fmtSz(b) { return b < 1048576 ? (b / 1024).toFixed(1) + ' KB' : (b / 1048576).toFixed(1) + ' MB'; }

    function showPreview(file) {
      selectedFile = file;
      prevName.textContent = file.name;
      prevSize.textContent = fmtSz(file.size) + ' ready';
      if (file.type.startsWith('image/')) {
        var r = new FileReader();
        r.onload = function (e) {
          prevThumb.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
        };
        r.readAsDataURL(file);
      } else {
        prevThumb.innerHTML = '📄';
      }
      prevBar.classList.add('show');
      uzone.style.opacity = '0.5';
      submitBtn.disabled = false;
    }

    function clearPreview() {
      selectedFile = null;
      fileInput.value = '';
      prevBar.classList.remove('show');
      uzone.style.opacity = '1';
      prevThumb.innerHTML = '🖼';
      submitBtn.disabled = true;
    }

    fileInput.addEventListener('change', function () {
      var f = fileInput.files[0];
      if (!f) return;
      if (f.size > 5 * 1024 * 1024) { showErr('File must be under 5 MB.'); fileInput.value = ''; return; }
      showPreview(f);
    });

    prevRm.addEventListener('click', clearPreview);

    /* Drag & drop */
    uzone.addEventListener('dragover', function (e) { e.preventDefault(); uzone.classList.add('il-over'); });
    uzone.addEventListener('dragleave', function () { uzone.classList.remove('il-over'); });
    uzone.addEventListener('drop', function (e) {
      e.preventDefault(); uzone.classList.remove('il-over');
      var f = e.dataTransfer.files[0];
      if (!f) return;
      if (f.size > 5 * 1024 * 1024) { showErr('File must be under 5 MB.'); return; }
      showPreview(f);
    });

    function showErr(msg) { errEl.textContent = msg; errEl.style.display = 'block'; }
    function hideErr() { errEl.style.display = 'none'; }

    /* Close handlers */
    document.getElementById('il-qr-close').addEventListener('click', _closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) _closeModal(); });
    document.addEventListener('keydown', function kd(e) {
      if (e.key === 'Escape') { _closeModal(); document.removeEventListener('keydown', kd); }
    });

    /* Submit */
    submitBtn.addEventListener('click', function () {
      hideErr();
      if (!selectedFile) { showErr('Please upload your payment screenshot.'); return; }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span style="animation:spin .8s linear infinite;display:inline-block">⟳</span> Verifying…';

      var fd = new FormData();
      fd.append('screenshot', selectedFile);
      fd.append('ref', ref);
      fd.append('amount', amount);
      fd.append('name', opts.name || '');
      fd.append('phone', opts.phone || '');
      fd.append('service', opts.service || '');

      fetch('backend/submit_payment.php', { method: 'POST', body: fd })
        .then(_parseJsonResponse)
        .then(function (d) {
          if (!d.success) throw new Error(d.message || 'Upload failed.');
          _closeModal();
          if (typeof opts.onSuccess === 'function') {
            opts.onSuccess(d.txn_id || ('QR_' + ref), ref);
          }
        })
        .catch(function (e) {
          showErr(e.message || 'Could not process payment. Please try again.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 2L3 7v5c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V7L12 2z"/><path d="M9 12l2 2 4-4"/></svg> Confirm Payment';
        });
    });
  }

  /* ── Close QR modal ──────────────────────────────────── */
  function _closeModal() {
    var overlay = document.getElementById('il-qr-overlay');
    if (overlay) {
      overlay.classList.remove('open');
      setTimeout(function () { if (overlay.parentNode) overlay.remove(); }, 300);
    }
  }

  window.ilSaveBooking = function () {
    /* PHP backend call happens in the page's onSuccess callback */
  };

  /* ── Helpers ─────────────────────────────────────────── */

  /** Generate a reference number like IGL-2025-84729 */
  function _genRef() {
    var y = new Date().getFullYear();
    var n = Math.floor(10000 + Math.random() * 89999);
    return 'IGL-' + y + '-' + n;
  }

  /* Spin animation for loading state */
  (function () {
    var s = document.createElement('style');
    s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(s);
  })();

  /* OTP Input Auto-Advance and Validation */
  window.ilSetupOtpInputs = function(otpInputIds) {
    if (!otpInputIds || !Array.isArray(otpInputIds)) {
      otpInputIds = ['o1', 'o2', 'o3', 'o4', 'o5', 'o6']; // Default for home page
    }
    
    otpInputIds.forEach(function(id, index) {
      var input = document.getElementById(id);
      if (!input) return;
      
      input.addEventListener('input', function(e) {
        // Only allow numeric input
        this.value = this.value.replace(/[^0-9]/g, '');
        
        // Auto-advance to next input
        if (this.value.length === 1 && index < otpInputIds.length - 1) {
          var nextInput = document.getElementById(otpInputIds[index + 1]);
          if (nextInput) nextInput.focus();
        }
      });
      
      input.addEventListener('keydown', function(e) {
        // Handle backspace to go to previous input
        if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
          var prevInput = document.getElementById(otpInputIds[index - 1]);
          if (prevInput) {
            prevInput.focus();
            e.preventDefault();
          }
        }
      });
      
      input.addEventListener('paste', function(e) {
        e.preventDefault();
        var paste = (e.clipboardData || window.clipboardData).getData('text');
        var digits = paste.replace(/[^0-9]/g, '').slice(0, 6);
        
        // Fill all inputs with pasted digits
        otpInputIds.forEach(function(otpId, i) {
          var inp = document.getElementById(otpId);
          if (inp && digits[i]) {
            inp.value = digits[i];
          }
        });
        
        // Focus on the next empty input or last input
        var nextIndex = Math.min(digits.length, otpInputIds.length - 1);
        var nextInput = document.getElementById(otpInputIds[nextIndex]);
        if (nextInput) nextInput.focus();
      });
    });
  };

})();
