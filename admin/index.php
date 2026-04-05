<?php
/**
 * Instalegl Admin — Login Page
 */
require_once __DIR__ . '/includes/guard.php';
session_start();

// Redirect if already logged in
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $pdo  = db();
            $stmt = $pdo->prepare("SELECT * FROM `admin_users` WHERE `username` = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch();

            $passwordOk = false;
            if ($admin) {
                $storedPassword = (string) ($admin['password'] ?? '');
                $passwordOk = password_verify($password, $storedPassword)
                    || hash_equals($storedPassword, $password);
            }

            if ($passwordOk) {
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_user'] = [
                    'id'        => $admin['id'],
                    'username'  => $admin['username'],
                    'full_name' => $admin['full_name'],
                    'role'      => $admin['role'],
                ];
                if (!password_get_info($admin['password'])['algo']) {
                    $pdo->prepare("UPDATE `admin_users` SET `password` = :password WHERE `id` = :id")
                        ->execute([
                            ':password' => password_hash($password, PASSWORD_DEFAULT),
                            ':id'       => $admin['id'],
                        ]);
                }

                $pdo->prepare("UPDATE `admin_users` SET `last_login` = NOW() WHERE `id` = :id")
                    ->execute([':id' => $admin['id']]);

                header('Location: dashboard.php'); exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'Database error. Please check your configuration.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Login — Instalegl</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--em:#046C4E;--em2:#059669;--em3:#10B981}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#0a0d18;color:#f4f1ec;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.login-wrap{width:100%;max-width:400px}
.logo{display:flex;align-items:center;gap:12px;margin-bottom:36px;justify-content:center}
.logo-mark{width:42px;height:42px;border-radius:11px;background:linear-gradient(135deg,#046C4E,#059669);display:flex;align-items:center;justify-content:center}
.logo-mark svg{width:22px;height:22px}
.logo span{font-size:20px;font-weight:800;color:#fff;letter-spacing:-.03em}
.logo small{font-size:11px;font-weight:700;color:var(--em3);letter-spacing:.1em;text-transform:uppercase;display:block;margin-top:1px}
.box{background:#131929;border:1px solid rgba(255,255,255,.07);border-radius:20px;padding:36px 32px}
.box h1{font-size:22px;font-weight:800;color:#fff;margin-bottom:6px;letter-spacing:-.02em}
.box p{font-size:13px;color:#9ba5c0;margin-bottom:28px}
.fg{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.fl{font-size:11px;font-weight:700;color:#9ba5c0;letter-spacing:.06em;text-transform:uppercase}
.fi{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px 15px;font-size:14px;color:#fff;font-family:inherit;outline:none;transition:all .2s;width:100%}
.fi:focus{border-color:rgba(4,108,78,.5);background:rgba(4,108,78,.04)}
.fi::placeholder{color:rgba(155,165,192,.4)}
.btn-login{width:100%;background:linear-gradient(135deg,#046C4E,#059669);color:#fff;border:none;border-radius:11px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;font-family:inherit;margin-top:8px;box-shadow:0 6px 20px rgba(4,108,78,.3)}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(4,108,78,.45)}
.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:11px 14px;font-size:13px;font-weight:600;color:#f87171;margin-bottom:16px}
.hint{text-align:center;font-size:11px;color:rgba(155,165,192,.4);margin-top:20px}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="logo">
    <div class="logo-mark">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round">
        <path d="M12 2L3 7v5c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V7L12 2z"/>
        <path d="M9 12l2 2 4-4"/>
      </svg>
    </div>
    <div><span>Instalegl</span><small>Admin Panel</small></div>
  </div>
  <div class="box">
    <h1>Sign In</h1>
    <p>Enter your admin credentials to access the panel.</p>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>

    <form method="POST" action="index.php" autocomplete="on">
      <div class="fg">
        <label class="fl" for="username">Username</label>
        <input class="fi" id="username" name="username" type="text" placeholder="admin" autocomplete="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="fg">
        <label class="fl" for="password">Password</label>
        <input class="fi" id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button class="btn-login" type="submit">Sign In →</button>
    </form>
    <p class="hint">Default credentials: admin / admin123<br>Change after first login.</p>
  </div>
</div>
</body>
</html>
