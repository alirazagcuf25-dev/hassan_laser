<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? 'admin');
    $phone = trim($_POST['phone'] ?? '');
    $name = trim($_POST['full_name'] ?? 'System Admin');
    $password = $_POST['password'] ?? '';

  if ($username === '' || $password === '') {
    $message = 'Username and password required';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);

    $check = $pdo->prepare('SELECT id FROM users WHERE full_name = ? OR role = "admin" LIMIT 1');
    $check->execute([$username]);
        $found = $check->fetch();

        if ($found) {
      $u = $pdo->prepare('UPDATE users SET full_name = ?, role = "admin", phone = ?, password_hash = ?, is_active = 1 WHERE id = ?');
      $u->execute([$username, $phone ?: null, $hash, (int)$found['id']]);
            $message = 'Admin updated successfully. You can now login.';
        } else {
            $i = $pdo->prepare('INSERT INTO users(role, full_name, phone, password_hash, is_active) VALUES("admin", ?, ?, ?, 1)');
      $i->execute([$username, $phone ?: null, $hash]);
            $message = 'Admin created successfully. You can now login.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Setup Admin</title>
  <style>
    body{font-family:Arial,sans-serif;max-width:520px;margin:40px auto;padding:20px}
    input,button{width:100%;padding:10px;margin-bottom:10px}
    .msg{padding:10px;background:#eef7ee;border:1px solid #b5d3b5}
  </style>
</head>
<body>
  <h2>One-Time Admin Setup</h2>
  <?php if ($message): ?><div class="msg"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
  <form method="post">
    <input type="text" name="username" placeholder="Admin Username" value="admin" required>
    <input type="text" name="full_name" placeholder="Display Name (optional)" value="System Admin">
    <input type="text" name="phone" placeholder="Phone (optional)">
    <input type="password" name="password" placeholder="Admin Password" required>
    <button type="submit">Save Admin</button>
  </form>
</body>
</html>
