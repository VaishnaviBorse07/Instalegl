<?php
/**
 * Shared admin nav/header HTML.
 * $pageTitle must be defined before including this file.
 */
$user = adminUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — Instalegl Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
:root{
  --bg:#0a0d18;--bg2:#0d1120;--card:#131929;--card2:#1a2038;
  --em:#046C4E;--em2:#059669;--em3:#10B981;--em4:#34d399;
  --text:#f4f1ec;--sub:#9ba5c0;--bdr:rgba(255,255,255,0.07);
  --r:12px;--sidebar:240px;
  --red:#ef4444;--amber:#f59e0b;--green:#10B981;
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex}
a{color:var(--em3);text-decoration:none}
a:hover{color:var(--em4)}
button{font-family:inherit;cursor:pointer}
/* ── Sidebar ── */
.sidebar{
  width:var(--sidebar);flex-shrink:0;background:var(--bg2);
  border-right:1px solid var(--bdr);display:flex;flex-direction:column;
  position:fixed;top:0;left:0;bottom:0;z-index:100;overflow-y:auto;
}
.sb-logo{
  display:flex;align-items:center;gap:10px;padding:20px 20px 16px;
  border-bottom:1px solid var(--bdr);text-decoration:none;
}
.sb-logo-mark{
  width:32px;height:32px;border-radius:8px;
  background:linear-gradient(135deg,var(--em),var(--em2));
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.sb-logo-mark svg{width:16px;height:16px}
.sb-logo span{font-size:15px;font-weight:800;color:#fff;letter-spacing:-.02em}
.sb-logo small{font-size:10px;font-weight:600;color:var(--em3);letter-spacing:.08em;text-transform:uppercase;display:block}
.sb-nav{flex:1;padding:16px 12px}
.sb-section{font-size:10px;font-weight:700;color:rgba(255,255,255,.25);letter-spacing:.12em;text-transform:uppercase;padding:14px 8px 8px;margin-top:8px}
.sb-section:first-of-type{margin-top:0;padding-top:0}
.sb-link{
  display:flex;align-items:center;gap:10px;
  padding:9px 10px;border-radius:9px;
  font-size:13px;font-weight:600;color:rgba(255,255,255,.55);
  text-decoration:none;transition:all .2s;margin-bottom:2px;
}
.sb-link:hover{color:#fff;background:rgba(255,255,255,.05)}
.sb-link.active{color:#fff;background:rgba(4,108,78,.18);border:1px solid rgba(4,108,78,.3)}
.sb-link svg{width:16px;height:16px;flex-shrink:0}
.sb-badge{
  margin-left:auto;background:var(--red);color:#fff;
  border-radius:100px;font-size:10px;font-weight:700;
  padding:1px 7px;min-width:18px;text-align:center;
}
.sb-badge.green{background:var(--green)}
.sb-footer{
  padding:16px 20px;border-top:1px solid var(--bdr);
  font-size:12px;color:var(--sub);
}
.sb-user{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.sb-avatar{
  width:34px;height:34px;border-radius:50%;
  background:linear-gradient(135deg,var(--em),var(--em2));
  display:flex;align-items:center;justify-content:center;
  font-size:13px;font-weight:700;color:#fff;flex-shrink:0;
}
.sb-user-info .name{font-size:13px;font-weight:700;color:#fff}
.sb-user-info .role{font-size:11px;color:var(--sub)}
.sb-logout{
  display:block;text-align:center;background:rgba(239,68,68,.12);
  border:1px solid rgba(239,68,68,.25);border-radius:8px;
  color:var(--red);font-size:12px;font-weight:700;
  padding:8px;text-decoration:none;transition:all .2s;
}
.sb-logout:hover{background:rgba(239,68,68,.22);color:var(--red)}
/* ── Main ── */
.main{margin-left:var(--sidebar);flex:1;min-height:100vh;display:flex;flex-direction:column}
.topbar{
  background:var(--bg2);border-bottom:1px solid var(--bdr);
  padding:0 32px;height:56px;display:flex;align-items:center;
  gap:16px;position:sticky;top:0;z-index:50;
}
.topbar-title{font-size:16px;font-weight:700;color:#fff;flex:1}
.topbar-sub{font-size:12px;color:var(--sub);margin-top:2px}
.page{padding:28px 32px;flex:1}
/* ── Cards ── */
.card{background:var(--card);border:1px solid var(--bdr);border-radius:16px;padding:22px 24px}
.card-title{font-size:13px;font-weight:700;color:#fff;margin-bottom:16px;display:flex;align-items:center;gap:8px}
/* ── Stat cards ── */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--card);border:1px solid var(--bdr);border-radius:16px;padding:20px 22px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:-20px;right:-20px;width:80px;height:80px;border-radius:50%;background:rgba(4,108,78,.06);pointer-events:none}
.stat-label{font-size:11px;font-weight:700;color:var(--sub);letter-spacing:.08em;text-transform:uppercase;margin-bottom:8px}
.stat-num{font-size:32px;font-weight:800;color:#fff;line-height:1;margin-bottom:4px;letter-spacing:-.02em}
.stat-sub{font-size:12px;color:var(--sub)}
.stat-icon{position:absolute;top:18px;right:18px;font-size:22px}
/* ── Tables ── */
.tbl-wrap{overflow-x:auto}
table.dtbl{width:100%;border-collapse:collapse;font-size:13px}
table.dtbl th{background:rgba(4,108,78,.08);color:var(--em3);font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:11px 14px;text-align:left;border-bottom:1px solid rgba(4,108,78,.2);white-space:nowrap}
table.dtbl td{padding:12px 14px;color:var(--sub);border-bottom:1px solid var(--bdr);vertical-align:middle}
table.dtbl tr:hover td{background:rgba(255,255,255,.02);color:var(--text)}
table.dtbl td strong{color:#fff}
/* ── Badges ── */
.badge{display:inline-flex;align-items:center;gap:4px;border-radius:100px;padding:3px 10px;font-size:11px;font-weight:700}
.badge-new{background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:#60a5fa}
.badge-progress{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#fbbf24}
.badge-done{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10B981}
.badge-rejected{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171}
.badge-pending{background:rgba(156,163,175,.12);border:1px solid rgba(156,163,175,.3);color:#9ca3af}
.badge-payment{background:rgba(251,146,60,.12);border:1px solid rgba(251,146,60,.3);color:#fb923c}
.badge-review{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#fbbf24}
.badge-approved{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10B981}
.badge-unread{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171}
.badge-read{background:rgba(156,163,175,.1);border:1px solid rgba(156,163,175,.2);color:var(--sub)}
/* ── Action buttons ── */
.btn{display:inline-flex;align-items:center;gap:6px;border-radius:9px;padding:7px 14px;font-size:12px;font-weight:700;border:none;transition:all .2s;text-decoration:none}
.btn-em{background:linear-gradient(135deg,var(--em),var(--em2));color:#fff;box-shadow:0 4px 12px rgba(4,108,78,.25)}
.btn-em:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(4,108,78,.4);color:#fff}
.btn-ghost{background:rgba(255,255,255,.06);border:1px solid var(--bdr);color:rgba(255,255,255,.65)}
.btn-ghost:hover{background:rgba(255,255,255,.1);color:#fff}
.btn-danger{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);color:var(--red)}
.btn-danger:hover{background:rgba(239,68,68,.22)}
.btn-success{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:var(--em3)}
.btn-success:hover{background:rgba(16,185,129,.22)}
/* ── Filters ── */
.filters{display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap}
.search-box{background:rgba(255,255,255,.05);border:1px solid var(--bdr);border-radius:9px;padding:9px 14px;font-size:13px;color:#fff;font-family:inherit;outline:none;transition:all .2s;min-width:220px}
.search-box::placeholder{color:rgba(255,255,255,.25)}
.search-box:focus{border-color:rgba(4,108,78,.45)}
select.filter-sel{background:rgba(255,255,255,.05);border:1px solid var(--bdr);border-radius:9px;padding:9px 14px;font-size:12px;color:#fff;font-family:inherit;outline:none;cursor:pointer}
select.filter-sel option{background:#1a2038}
/* ── Pagination ── */
.pagination{display:flex;gap:6px;margin-top:18px;justify-content:flex-end}
.pg-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;font-size:12px;font-weight:600;color:var(--sub);background:rgba(255,255,255,.04);border:1px solid var(--bdr);text-decoration:none;transition:all .2s}
.pg-btn:hover,.pg-btn.active{background:rgba(4,108,78,.18);border-color:rgba(4,108,78,.35);color:#fff}
/* ── Alerts ── */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:16px}
.alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:var(--em3)}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--red)}
/* ── Detail sections ── */
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
.detail-item{background:rgba(255,255,255,.03);border:1px solid var(--bdr);border-radius:10px;padding:12px 14px}
.detail-label{font-size:10px;font-weight:700;color:var(--em3);letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
.detail-val{font-size:13px;font-weight:600;color:#fff;word-break:break-all}
/* ── Modal ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:var(--card);border:1px solid var(--bdr);border-radius:20px;padding:28px 32px;max-width:580px;width:90%;max-height:80vh;overflow-y:auto;position:relative}
.modal h3{font-size:17px;font-weight:700;color:#fff;margin-bottom:18px}
.modal-close{position:absolute;top:16px;right:16px;background:rgba(255,255,255,.07);border:none;border-radius:7px;width:30px;height:30px;color:var(--sub);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s}
.modal-close:hover{color:#fff;background:rgba(255,255,255,.12)}
/* ── Toast ── */
.toast{position:fixed;bottom:24px;right:24px;z-index:9999;background:#111;border:1px solid var(--bdr);border-radius:12px;padding:12px 18px;font-size:13px;font-weight:600;color:#fff;box-shadow:0 8px 32px rgba(0,0,0,.5);transform:translateY(80px);opacity:0;transition:all .3s}
.toast.show{transform:translateY(0);opacity:1}
.toast.ok{border-color:rgba(16,185,129,.4);color:var(--em3)}
.toast.err{border-color:rgba(239,68,68,.4);color:var(--red)}
/* ── Responsive ── */
@media(max-width:1024px){.stat-grid{grid-template-columns:1fr 1fr}}
@media(max-width:800px){.sidebar{display:none}.main{margin-left:0}}
</style>
</head>
<body>
<nav class="sidebar">
  <a href="dashboard.php" class="sb-logo">
    <div class="sb-logo-mark">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round">
        <path d="M12 2L3 7v5c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V7L12 2z"/>
        <path d="M9 12l2 2 4-4"/>
      </svg>
    </div>
    <div><span>Instalegl</span><small>Admin Panel</small></div>
  </a>
  <div class="sb-nav">
    <div class="sb-section">Overview</div>
    <a href="dashboard.php" class="sb-link <?= ($currentPage??'')==='dashboard'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <div class="sb-section">Management</div>
    <a href="bookings.php" class="sb-link <?= ($currentPage??'')==='bookings'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      Bookings
      <?php if(!empty($counts['new_bookings'])): ?><span class="sb-badge"><?= $counts['new_bookings'] ?></span><?php endif ?>
    </a>
    <a href="contacts.php" class="sb-link <?= ($currentPage??'')==='contacts'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
      Messages
      <?php if(!empty($counts['unread_messages'])): ?><span class="sb-badge"><?= $counts['unread_messages'] ?></span><?php endif ?>
    </a>
    <a href="advocates.php" class="sb-link <?= ($currentPage??'')==='advocates'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Advocates
      <?php if(!empty($counts['pending_advocates'])): ?><span class="sb-badge"><?= $counts['pending_advocates'] ?></span><?php endif ?>
    </a>
    <div class="sb-section">Account</div>
    <a href="../index.html" target="_blank" class="sb-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      View Website
    </a>
  </div>
  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr($user['full_name'] ?? 'A',0,1)) ?></div>
      <div class="sb-user-info">
        <div class="name"><?= htmlspecialchars($user['full_name'] ?? 'Admin') ?></div>
        <div class="role"><?= htmlspecialchars($user['role'] ?? 'admin') ?></div>
      </div>
    </div>
    <a href="logout.php" class="sb-logout">⏻ Sign Out</a>
  </div>
</nav>
