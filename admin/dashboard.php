<?php
/**
 * Instalegl Admin — Dashboard
 */
require_once __DIR__ . '/includes/guard.php';
requireLogin();

$pdo = db();
$currentPage = 'dashboard';

// Aggregate counts for sidebar badges and stats
$counts = [];
$counts['new_bookings']        = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn();
$counts['payment_pending']     = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='payment_pending'")->fetchColumn();
$counts['unread_messages']     = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
$counts['pending_advocates']   = (int)$pdo->query("SELECT COUNT(*) FROM advocate_applications WHERE status='pending'")->fetchColumn();

$stats = [
    'total_bookings'   => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'today_bookings'   => $pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
    'total_advocates'  => $pdo->query("SELECT COUNT(*) FROM advocate_applications")->fetchColumn(),
    'total_messages'   => $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn(),
];

// Recent bookings
$recentBookings = $pdo->query(
    "SELECT * FROM bookings ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

// Recent advocate applications
$recentAdvocates = $pdo->query(
    "SELECT * FROM advocate_applications ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

$statusBadge = function(string $s): string {
    $map = [
        'otp_pending'     => 'badge-pending',
        'new'             => 'badge-new',
        'payment_pending' => 'badge-payment',
        'in_progress'     => 'badge-progress',
        'completed'       => 'badge-done',
        'cancelled'       => 'badge-rejected',
        'pending'         => 'badge-pending',
        'under_review'    => 'badge-review',
        'approved'        => 'badge-approved',
        'rejected'        => 'badge-rejected',
    ];
    $label = ucwords(str_replace('_',' ',$s));
    $cls   = $map[$s] ?? 'badge-pending';
    return "<span class='badge {$cls}'>{$label}</span>";
};
?>
<div class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title">Dashboard</div>
      <div class="topbar-sub">Welcome back, <?= htmlspecialchars(adminUser()['full_name'] ?? 'Admin') ?> — <?= date('l, d M Y') ?></div>
    </div>
  </div>
  <div class="page">

    <!-- Stat Cards -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">Total Bookings</div>
        <div class="stat-num"><?= number_format($stats['total_bookings']) ?></div>
        <div class="stat-sub"><?= $counts['new_bookings'] ?> new · <?= $counts['payment_pending'] ?> payment pending</div>
        <div class="stat-icon">📋</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Today's Bookings</div>
        <div class="stat-num"><?= number_format($stats['today_bookings']) ?></div>
        <div class="stat-sub">Received today</div>
        <div class="stat-icon">📅</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Advocate Applications</div>
        <div class="stat-num"><?= number_format($stats['total_advocates']) ?></div>
        <div class="stat-sub"><?= $counts['pending_advocates'] ?> pending review</div>
        <div class="stat-icon">⚖️</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Contact Messages</div>
        <div class="stat-num"><?= number_format($stats['total_messages']) ?></div>
        <div class="stat-sub"><?= $counts['unread_messages'] ?> unread</div>
        <div class="stat-icon">💬</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px">

      <!-- Recent Bookings -->
      <div class="card">
        <div class="card-title">
          📋 Recent Bookings
          <a href="bookings.php" class="btn btn-ghost" style="font-size:11px;padding:5px 12px;margin-left:auto">View All</a>
        </div>
        <div class="tbl-wrap">
          <table class="dtbl">
            <thead><tr><th>Ref</th><th>Name</th><th>Service</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php if (!$recentBookings): ?>
              <tr><td colspan="5" style="text-align:center;color:#9ba5c0;padding:24px">No bookings yet.</td></tr>
            <?php else: foreach ($recentBookings as $b): ?>
              <tr>
                <td><strong><?= htmlspecialchars($b['ref_number']) ?></strong></td>
                <td><?= htmlspecialchars($b['full_name']) ?></td>
                <td style="max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($b['service']) ?></td>
                <td><?= $statusBadge($b['status']) ?></td>
                <td><?= date('d M', strtotime($b['created_at'])) ?></td>
              </tr>
            <?php endforeach; endif ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Advocate Applications -->
      <div class="card">
        <div class="card-title">
          ⚖️ Recent Applications
          <a href="advocates.php" class="btn btn-ghost" style="font-size:11px;padding:5px 12px;margin-left:auto">View All</a>
        </div>
        <div class="tbl-wrap">
          <table class="dtbl">
            <thead><tr><th>Name</th><th>City</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$recentAdvocates): ?>
              <tr><td colspan="3" style="text-align:center;color:#9ba5c0;padding:24px">No applications.</td></tr>
            <?php else: foreach ($recentAdvocates as $a): ?>
              <tr>
                <td><a href="advocate-view.php?id=<?= $a['id'] ?>" style="color:#10B981;font-weight:600"><?= htmlspecialchars($a['full_name']) ?></a></td>
                <td><?= htmlspecialchars($a['city']) ?></td>
                <td><?= $statusBadge($a['status']) ?></td>
              </tr>
            <?php endforeach; endif ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /grid -->
  </div><!-- /page -->
</div><!-- /main -->
</body></html>
