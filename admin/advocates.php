<?php
/**
 * Instalegl Admin — Advocate Applications List
 */
require_once __DIR__ . '/includes/guard.php';
requireLogin();

$pdo = db();
$currentPage = 'advocates';

$filter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page-1)*$perPage;

$where = "WHERE 1=1";
$params = [];
if (in_array($filter,['otp_pending','pending','under_review','approved','rejected'],true)) {
    $where .= " AND status=:status";
    $params[':status'] = $filter;
}
if ($search) {
    $where .= " AND (full_name LIKE :q OR email LIKE :q OR enrollment_no LIKE :q OR city LIKE :q)";
    $params[':q'] = "%{$search}%";
}

$cs = $pdo->prepare("SELECT COUNT(*) FROM advocate_applications $where");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$pages = max(1, ceil($total/$perPage));

$stmt = $pdo->prepare("SELECT * FROM advocate_applications $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$apps = $stmt->fetchAll();

$counts = [
    'new_bookings'      => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn(),
    'unread_messages'   => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
    'pending_advocates' => (int)$pdo->query("SELECT COUNT(*) FROM advocate_applications WHERE status='pending'")->fetchColumn(),
];

$pageTitle = 'Advocate Applications';
include __DIR__ . '/includes/header.php';

$statusBadge = function(string $s): string {
    $map = ['otp_pending'=>'badge-pending','pending'=>'badge-pending','under_review'=>'badge-review','approved'=>'badge-approved','rejected'=>'badge-rejected'];
    $cls = $map[$s] ?? 'badge-pending';
    $label = ucwords(str_replace('_',' ',$s));
    return "<span class='badge {$cls}'>{$label}</span>";
};
?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Advocate Applications</div>
    <span style="font-size:12px;color:#9ba5c0"><?= $total ?> total · <?= $counts['pending_advocates'] ?> pending</span>
  </div>
  <div class="page">

    <div class="filters">
      <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
        <input class="search-box" name="q" type="search" placeholder="Search name, email, enroll no…" value="<?= htmlspecialchars($search) ?>">
        <select class="filter-sel" name="status" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="otp_pending" <?= $filter==='otp_pending'?'selected':'' ?>>OTP Pending</option>
          <option value="pending" <?= $filter==='pending'?'selected':'' ?>>Pending</option>
          <option value="under_review" <?= $filter==='under_review'?'selected':'' ?>>Under Review</option>
          <option value="approved" <?= $filter==='approved'?'selected':'' ?>>Approved</option>
          <option value="rejected" <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
        </select>
        <button class="btn btn-em" type="submit">Search</button>
        <?php if ($search||$filter): ?><a href="advocates.php" class="btn btn-ghost">Clear</a><?php endif ?>
      </form>
    </div>

    <div class="card">
      <div class="tbl-wrap">
        <table class="dtbl">
          <thead><tr><th>App ID</th><th>Name</th><th>City</th><th>Bar Council</th><th>Enroll No.</th><th>Status</th><th>Applied</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if (!$apps): ?>
            <tr><td colspan="8" style="text-align:center;padding:32px;color:#9ba5c0">No applications yet.</td></tr>
          <?php else: foreach ($apps as $a): ?>
            <tr>
              <td><strong style="font-size:11px"><?= htmlspecialchars($a['app_id']) ?></strong></td>
              <td><?= htmlspecialchars($a['full_name']) ?></td>
              <td><?= htmlspecialchars($a['city']) ?></td>
              <td style="max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px"><?= htmlspecialchars($a['bar_council']) ?></td>
              <td><?= htmlspecialchars($a['enrollment_no']) ?></td>
              <td><?= $statusBadge($a['status']) ?></td>
              <td><?= date('d M Y', strtotime($a['created_at'])) ?></td>
              <td>
                <a href="advocate-view.php?id=<?= $a['id'] ?>" class="btn btn-em" style="font-size:11px">View →</a>
              </td>
            </tr>
          <?php endforeach; endif ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i=1;$i<=$pages;$i++): ?>
          <a href="?q=<?= urlencode($search) ?>&status=<?= urlencode($filter) ?>&page=<?= $i ?>"
             class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor ?>
      </div>
      <?php endif ?>
    </div>
  </div>
</div>
</body></html>
