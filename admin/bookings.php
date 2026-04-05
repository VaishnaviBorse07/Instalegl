<?php
/**
 * Instalegl Admin — Manage Bookings
 */
require_once __DIR__ . '/includes/guard.php';
requireLogin();

$pdo = db();
$currentPage = 'bookings';

// ── Status update via POST ───────────────────────────────────
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'];
    $allowed = ['otp_pending','new','payment_pending','in_progress','completed','cancelled'];

    if (in_array($action, $allowed, true) && $id > 0) {
        $pdo->prepare("UPDATE bookings SET status=:s WHERE id=:id")
            ->execute([':s' => $action, ':id' => $id]);
        $flash = 'Status updated successfully.';
    } elseif ($action === 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM bookings WHERE id=:id")->execute([':id' => $id]);
        $flash = 'Booking deleted.';
    }
}

// ── Filters ─────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (full_name LIKE :q OR phone LIKE :q OR ref_number LIKE :q)";
    $params[':q'] = "%{$search}%";
}
if (in_array($filter, ['otp_pending','new','payment_pending','in_progress','completed','cancelled'], true)) {
    $where .= " AND status = :status";
    $params[':status'] = $filter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM bookings $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Counts for sidebar badges
$counts = [
    'new_bookings'      => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn(),
    'unread_messages'   => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
    'pending_advocates' => (int)$pdo->query("SELECT COUNT(*) FROM advocate_applications WHERE status='pending'")->fetchColumn(),
];

$pageTitle = 'Bookings';
include __DIR__ . '/includes/header.php';

$statusBadge = function(string $s): string {
    $map = [
        'otp_pending'     => 'badge-pending',
        'new'             => 'badge-new',
        'payment_pending' => 'badge-payment',
        'in_progress'     => 'badge-progress',
        'completed'       => 'badge-done',
        'cancelled'       => 'badge-rejected',
    ];
    $cls = $map[$s] ?? 'badge-pending';
    $label = ucwords(str_replace('_',' ',$s));
    return "<span class='badge {$cls}'>{$label}</span>";
};
?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Client Bookings</div>
    <span style="font-size:12px;color:#9ba5c0"><?= $total ?> total</span>
  </div>
  <div class="page">
    <?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif ?>

    <div class="filters">
      <form method="GET" action="" style="display:flex;gap:10px;flex-wrap:wrap">
        <input class="search-box" name="q" type="search" placeholder="Search name, phone, ref…" value="<?= htmlspecialchars($search) ?>">
        <select class="filter-sel" name="status" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="otp_pending" <?= $filter==='otp_pending'?'selected':'' ?>>OTP Pending</option>
          <option value="new" <?= $filter==='new'?'selected':'' ?>>New</option>
          <option value="payment_pending" <?= $filter==='payment_pending'?'selected':'' ?>>Payment Pending</option>
          <option value="in_progress" <?= $filter==='in_progress'?'selected':'' ?>>In Progress</option>
          <option value="completed" <?= $filter==='completed'?'selected':'' ?>>Completed</option>
          <option value="cancelled" <?= $filter==='cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
        <button class="btn btn-em" type="submit">Search</button>
        <?php if ($search||$filter): ?>
          <a href="bookings.php" class="btn btn-ghost">Clear</a>
        <?php endif ?>
      </form>
    </div>

    <div class="card">
      <div class="tbl-wrap">
        <table class="dtbl">
          <thead>
            <tr>
              <th>Ref</th><th>Name</th><th>Phone</th>
              <th>Service</th><th>Status</th><th>Payment</th><th>Received</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$bookings): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:#9ba5c0">No bookings found.</td></tr>
          <?php else: foreach ($bookings as $b): ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['ref_number']) ?></strong></td>
              <td><?= htmlspecialchars($b['full_name']) ?></td>
              <td><?= htmlspecialchars($b['phone']) ?></td>
              <td style="max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($b['service']) ?></td>
              <td><?= $statusBadge($b['status']) ?></td>
              <td>
                <?php if (!empty($b['payment_screenshot'])): ?>
                  <a href="../uploads/<?= htmlspecialchars($b['payment_screenshot']) ?>" target="_blank"
                     title="View payment screenshot"
                     style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;
                            color:#10B981;text-decoration:none;background:rgba(4,108,78,.1);
                            border:1px solid rgba(4,108,78,.25);border-radius:6px;padding:4px 9px">
                    📸 Screenshot
                  </a>
                  <?php if (!empty($b['payment_txn_id'])): ?>
                    <div style="font-size:10px;color:#9ba5c0;margin-top:3px"><?= htmlspecialchars($b['payment_txn_id']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($b['payment_amount'])): ?>
                    <div style="font-size:10px;color:#10B981;font-weight:700">₹<?= number_format((float)$b['payment_amount'], 2) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="font-size:11px;color:rgba(155,165,192,.4)">—</span>
                <?php endif; ?>
              </td>
              <td><?= date('d M Y H:i', strtotime($b['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <select name="action" onchange="this.form.submit()" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:5px 8px;font-size:11px;color:#fff;font-family:inherit;cursor:pointer;outline:none">
                      <option value="">Change…</option>
                      <option value="new">→ New</option>
                      <option value="payment_pending">→ Payment Pending</option>
                      <option value="in_progress">→ In Progress</option>
                      <option value="completed">→ Completed</option>
                      <option value="cancelled">→ Cancelled</option>
                    </select>
                  </form>
                  <form method="POST" onsubmit="return confirm('Delete this booking?')">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn btn-danger" type="submit">✕</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?q=<?= urlencode($search) ?>&status=<?= urlencode($filter) ?>&page=<?= $i ?>"
             class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor ?>
      </div>
      <?php endif ?>
    </div>
  </div>
</div>
</body></html>
