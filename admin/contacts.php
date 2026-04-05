<?php
/**
 * Instalegl Admin — Contact Messages
 */
require_once __DIR__ . '/includes/guard.php';
requireLogin();

$pdo = db();
$currentPage = 'contacts';

// Mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'read' && $id)   $pdo->prepare("UPDATE contact_messages SET is_read=1 WHERE id=:id")->execute([':id'=>$id]);
    if ($action === 'unread' && $id) $pdo->prepare("UPDATE contact_messages SET is_read=0 WHERE id=:id")->execute([':id'=>$id]);
    if ($action === 'delete' && $id) $pdo->prepare("DELETE FROM contact_messages WHERE id=:id")->execute([':id'=>$id]);
    header('Location: contacts.php'); exit;
}

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page-1)*$perPage;

$where = "WHERE 1=1";
$params = [];
if ($filter === 'unread') { $where .= " AND is_read=0"; }
if ($filter === 'read')   { $where .= " AND is_read=1"; }
if ($search) {
    $where .= " AND (full_name LIKE :q OR email LIKE :q OR subject LIKE :q)";
    $params[':q'] = "%{$search}%";
}
$cs = $pdo->prepare("SELECT COUNT(*) FROM contact_messages $where");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM contact_messages $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$msgs = $stmt->fetchAll();

$counts = [
    'new_bookings'      => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn(),
    'unread_messages'   => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
    'pending_advocates' => (int)$pdo->query("SELECT COUNT(*) FROM advocate_applications WHERE status='pending'")->fetchColumn(),
];

$pageTitle = 'Contact Messages';
include __DIR__ . '/includes/header.php';
?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Contact Messages</div>
    <span style="font-size:12px;color:#9ba5c0"><?= $total ?> messages · <?= $counts['unread_messages'] ?> unread</span>
  </div>
  <div class="page">

    <div class="filters">
      <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
        <input class="search-box" name="q" type="search" placeholder="Search name, email, subject…" value="<?= htmlspecialchars($search) ?>">
        <select class="filter-sel" name="filter" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="unread" <?= $filter==='unread'?'selected':'' ?>>Unread Only</option>
          <option value="read" <?= $filter==='read'?'selected':'' ?>>Read</option>
        </select>
        <button class="btn btn-em" type="submit">Filter</button>
        <?php if ($search||$filter): ?><a href="contacts.php" class="btn btn-ghost">Clear</a><?php endif ?>
      </form>
    </div>

    <div class="card">
      <div class="tbl-wrap">
        <table class="dtbl">
          <thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if (!$msgs): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:#9ba5c0">No messages yet.</td></tr>
          <?php else: foreach ($msgs as $m): ?>
            <tr style="<?= !$m['is_read']?'background:rgba(4,108,78,.03)':'' ?>">
              <td><strong><?= htmlspecialchars($m['full_name']) ?></strong></td>
              <td><a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a></td>
              <td><?= htmlspecialchars($m['subject']) ?></td>
              <td>
                <span style="color:#9ba5c0;font-size:12px">
                  <?= htmlspecialchars(mb_strimwidth($m['message'], 0, 60, '…')) ?>
                </span>
                <button class="btn btn-ghost" style="font-size:10px;padding:3px 8px;margin-left:6px" onclick="openMsg(<?= $m['id'] ?>, <?= json_encode(htmlspecialchars($m['full_name'])) ?>, <?= json_encode(htmlspecialchars($m['subject'])) ?>, <?= json_encode(htmlspecialchars($m['message'])) ?>)">View</button>
              </td>
              <td><?= $m['is_read'] ? "<span class='badge badge-read'>Read</span>" : "<span class='badge badge-unread'>Unread</span>" ?></td>
              <td><?= date('d M Y', strtotime($m['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:6px">
                  <form method="POST">
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <input type="hidden" name="action" value="<?= $m['is_read']?'unread':'read' ?>">
                    <button class="btn btn-ghost" type="submit" title="<?= $m['is_read']?'Mark unread':'Mark read' ?>"><?= $m['is_read']?'◉':'✓' ?></button>
                  </form>
                  <form method="POST" onsubmit="return confirm('Delete this message?')">
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
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
      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i=1;$i<=$pages;$i++): ?>
          <a href="?q=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&page=<?= $i ?>"
             class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor ?>
      </div>
      <?php endif ?>
    </div>
  </div>
</div>

<!-- Message Modal -->
<div class="modal-overlay" id="msgModal">
  <div class="modal">
    <button class="modal-close" onclick="document.getElementById('msgModal').classList.remove('open')">×</button>
    <div style="font-size:11px;font-weight:700;color:#10B981;letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px" id="modalSubject"></div>
    <div style="font-size:16px;font-weight:700;color:#fff;margin-bottom:16px" id="modalName"></div>
    <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:18px;font-size:13px;color:#9ba5c0;line-height:1.85;white-space:pre-wrap" id="modalBody"></div>
  </div>
</div>

<script>
function openMsg(id, name, subject, body) {
  document.getElementById('modalName').textContent    = name;
  document.getElementById('modalSubject').textContent = subject;
  document.getElementById('modalBody').textContent    = body;
  document.getElementById('msgModal').classList.add('open');
  // auto-mark read
  fetch('contacts.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'id='+id+'&action=read'
  });
}
document.getElementById('msgModal').addEventListener('click', function(e){
  if(e.target===this) this.classList.remove('open');
});
</script>
</body></html>
