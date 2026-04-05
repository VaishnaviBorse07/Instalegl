<?php
/**
 * Instalegl Admin — Advocate Application Detail View
 */
require_once __DIR__ . '/includes/guard.php';
requireLogin();

$pdo = db();
$currentPage = 'advocates';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: advocates.php'); exit; }

$app = $pdo->prepare("SELECT * FROM advocate_applications WHERE id=:id LIMIT 1");
$app->execute([':id' => $id]);
$a = $app->fetch();
if (!$a) { header('Location: advocates.php'); exit; }

// ── Status change ────────────────────────────────────────────
$flash = ''; $flashType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes  = trim($_POST['admin_notes'] ?? '');
    $allowed = ['pending','under_review','approved','rejected'];
    if (in_array($action, $allowed, true)) {
        $pdo->prepare("UPDATE advocate_applications SET status=:s, admin_notes=:n, reviewed_by=:rb, reviewed_at=NOW() WHERE id=:id")
            ->execute([':s'=>$action,':n'=>$notes,':rb'=>$_SESSION['admin_id'],':id'=>$id]);
        $flash = 'Application status updated to ' . ucwords(str_replace('_',' ',$action)) . '.';
        // Refresh
        $app->execute([':id'=>$id]);
        $a = $app->fetch();
    }
}

$counts = [
    'new_bookings'      => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn(),
    'unread_messages'   => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
    'pending_advocates' => (int)$pdo->query("SELECT COUNT(*) FROM advocate_applications WHERE status='pending'")->fetchColumn(),
];

$pageTitle = 'Advocate — ' . $a['full_name'];
include __DIR__ . '/includes/header.php';

$statusBadge = function(string $s): string {
    $map = ['otp_pending'=>'badge-pending','pending'=>'badge-pending','under_review'=>'badge-review','approved'=>'badge-approved','rejected'=>'badge-rejected'];
    $cls = $map[$s] ?? 'badge-pending';
    $label = ucwords(str_replace('_',' ',$s));
    return "<span class='badge {$cls}' style='font-size:13px;padding:5px 14px'>{$label}</span>";
};

function docLink(string $path, string $label): string {
    if (!$path) return '<span style="color:#9ba5c0;font-size:12px">Not uploaded</span>';
    $url = '../uploads/advocate-docs/' . ltrim($path, '/');
    return "<a href='" . htmlspecialchars($url) . "' target='_blank' class='btn btn-ghost' style='font-size:11px'>📄 " . htmlspecialchars($label) . "</a>";
}
?>
<div class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title">
        <a href="advocates.php" style="color:#9ba5c0;font-size:12px;margin-right:8px">← Advocates</a>
        <?= htmlspecialchars($a['full_name']) ?>
      </div>
      <div class="topbar-sub"><?= htmlspecialchars($a['app_id']) ?> · Applied <?= date('d M Y H:i', strtotime($a['created_at'])) ?></div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <?= $statusBadge($a['status']) ?>
    </div>
  </div>
  <div class="page">
    <?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

      <!-- LEFT: All details -->
      <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Personal -->
        <div class="card">
          <div class="card-title">👤 Personal Information</div>
          <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">Full Name</div><div class="detail-val"><?= htmlspecialchars($a['full_name']) ?></div></div>
            <div class="detail-item"><div class="detail-label">Date of Birth</div><div class="detail-val"><?= $a['dob'] ? date('d M Y', strtotime($a['dob'])) : '—' ?></div></div>
            <div class="detail-item"><div class="detail-label">Email</div><div class="detail-val"><a href="mailto:<?= htmlspecialchars($a['email']) ?>"><?= htmlspecialchars($a['email']) ?></a></div></div>
            <div class="detail-item"><div class="detail-label">Phone</div><div class="detail-val"><a href="tel:<?= htmlspecialchars($a['phone']) ?>"><?= htmlspecialchars($a['phone']) ?></a></div></div>
            <div class="detail-item"><div class="detail-label">City</div><div class="detail-val"><?= htmlspecialchars($a['city']) ?></div></div>
            <div class="detail-item"><div class="detail-label">IP Address</div><div class="detail-val"><?= htmlspecialchars($a['ip_address'] ?? '—') ?></div></div>
          </div>
          <?php if ($a['address']): ?>
          <div class="detail-item" style="margin-top:10px"><div class="detail-label">Office Address</div><div class="detail-val" style="font-weight:400;color:#9ba5c0"><?= nl2br(htmlspecialchars($a['address'])) ?></div></div>
          <?php endif ?>
        </div>

        <!-- Practice -->
        <div class="card">
          <div class="card-title">⚖️ Practice & Enrollment</div>
          <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">Bar Council</div><div class="detail-val"><?= htmlspecialchars($a['bar_council']) ?></div></div>
            <div class="detail-item"><div class="detail-label">Enrollment No.</div><div class="detail-val"><?= htmlspecialchars($a['enrollment_no']) ?></div></div>
            <div class="detail-item"><div class="detail-label">Enrollment Date</div><div class="detail-val"><?= $a['enrollment_date'] ? date('d M Y', strtotime($a['enrollment_date'])) : '—' ?></div></div>
            <div class="detail-item"><div class="detail-label">Years of Practice</div><div class="detail-val"><?= htmlspecialchars($a['years_practice']) ?></div></div>
            <div class="detail-item" style="grid-column:1/-1"><div class="detail-label">Practice Areas</div><div class="detail-val"><?= htmlspecialchars($a['practice_areas']) ?></div></div>
            <?php if ($a['courts']): ?><div class="detail-item"><div class="detail-label">Courts</div><div class="detail-val"><?= htmlspecialchars($a['courts']) ?></div></div><?php endif ?>
            <?php if ($a['languages']): ?><div class="detail-item"><div class="detail-label">Languages</div><div class="detail-val"><?= htmlspecialchars($a['languages']) ?></div></div><?php endif ?>
          </div>
          <?php if ($a['bio']): ?>
          <div class="detail-item" style="margin-top:10px"><div class="detail-label">Professional Bio</div><div class="detail-val" style="font-weight:400;color:#9ba5c0;line-height:1.75"><?= nl2br(htmlspecialchars($a['bio'])) ?></div></div>
          <?php endif ?>
        </div>

        <!-- Documents -->
        <div class="card">
          <div class="card-title">📂 Uploaded Documents</div>
          <div style="display:flex;flex-direction:column;gap:12px">
            <div style="display:flex;align-items:center;gap:12px;justify-content:space-between;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:12px 16px">
              <span style="font-size:13px;color:#e8e4df">Bar Council ID — Front</span>
              <?= docLink($a['doc_bc_front'], 'Download') ?>
            </div>
            <div style="display:flex;align-items:center;gap:12px;justify-content:space-between;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:12px 16px">
              <span style="font-size:13px;color:#e8e4df">Bar Council ID — Back</span>
              <?= docLink($a['doc_bc_back'], 'Download') ?>
            </div>
            <div style="display:flex;align-items:center;gap:12px;justify-content:space-between;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:12px 16px">
              <span style="font-size:13px;color:#e8e4df">Enrollment Certificate</span>
              <?= docLink($a['doc_cert'], 'Download') ?>
            </div>
            <div style="display:flex;align-items:center;gap:12px;justify-content:space-between;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:12px 16px">
              <span style="font-size:13px;color:#e8e4df">Government ID (<?= htmlspecialchars($a['gov_id_type'] ?? '') ?>)</span>
              <?= docLink($a['doc_govid'], 'Download') ?>
            </div>
            <div style="display:flex;align-items:center;gap:12px;justify-content:space-between;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:12px 16px">
              <span style="font-size:13px;color:#e8e4df">Profile Photo</span>
              <?= docLink($a['doc_photo'], 'View Photo') ?>
            </div>
            <div style="display:flex;align-items:center;gap:12px;justify-content:space-between;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:12px 16px">
              <span style="font-size:13px;color:#e8e4df">Payment QR Code</span>
              <?= docLink($a['doc_qr_code'], 'View QR Code') ?>
            </div>
          </div>
        </div>

      </div><!-- /left -->

      <!-- RIGHT: Status control -->
      <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:72px">

        <div class="card">
          <div class="card-title">🔧 Update Status</div>
          <form method="POST">
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px">
              <?php
              $buttons = [
                'approved'     => ['btn-success', '✓ Approve Application'],
                'under_review' => ['btn-ghost',   '🔍 Mark Under Review'],
                'rejected'     => ['btn-danger',  '✕ Reject Application'],
                'pending'      => ['btn-ghost',   '↩ Reset to Pending'],
              ];
              foreach ($buttons as $action => [$cls, $label]):
                if ($a['status'] === $action) continue;
              ?>
              <button type="submit" name="action" value="<?= $action ?>" class="btn <?= $cls ?>" style="justify-content:center;padding:10px">
                <?= $label ?>
              </button>
              <?php endforeach ?>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">
              <label style="font-size:11px;font-weight:700;color:#9ba5c0;letter-spacing:.06em;text-transform:uppercase">Admin Notes</label>
              <textarea name="admin_notes" rows="4" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:10px 12px;font-size:13px;color:#fff;font-family:inherit;outline:none;resize:vertical;width:100%;" placeholder="Optional internal notes…"><?= htmlspecialchars($a['admin_notes'] ?? '') ?></textarea>
            </div>
          </form>
        </div>

        <div class="card">
          <div class="card-title">📋 Application Info</div>
          <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;color:#9ba5c0">
            <div style="display:flex;justify-content:space-between"><span>Application ID</span><strong style="color:#10B981"><?= htmlspecialchars($a['app_id']) ?></strong></div>
            <div style="display:flex;justify-content:space-between"><span>Submitted</span><strong><?= date('d M Y', strtotime($a['created_at'])) ?></strong></div>
            <div style="display:flex;justify-content:space-between"><span>Last Updated</span><strong><?= date('d M Y', strtotime($a['updated_at'])) ?></strong></div>
            <?php if ($a['reviewed_at']): ?>
            <div style="display:flex;justify-content:space-between"><span>Reviewed</span><strong><?= date('d M Y', strtotime($a['reviewed_at'])) ?></strong></div>
            <?php endif ?>
          </div>
        </div>

      </div><!-- /right -->

    </div>
  </div>
</div>
</body></html>
