<?php
require __DIR__ . '/config.php';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $chosen_role = $_POST['role'] ?? '';

    $users = get_users();
    if (isset($users[$u]) && password_verify($p, $users[$u]['hash'])) {
        if ($chosen_role !== $users[$u]['role']) {
            header("Location: login.php?error=" . urlencode("Role mismatch. Your stored role is '{$users[$u]['role']}'."));
            exit;
        }
        $_SESSION['user'] = ['username'=>$u, 'role'=>$users[$u]['role']];
        header("Location: dashboard.php");
        exit;
    } else {
        header("Location: login.php?error=" . urlencode("Invalid username or password."));
        exit;
    }
}

// Render login form
render_header("Login");
$error = $_GET['error'] ?? '';
echo '<h1>Log in</h1><p class="sub">Choose your role and sign in.</p>';
if ($error) echo '<div class="msg">'.h($error).'</div>';
?>
<form method="post" action="login.php">
  <div>
    <label>Username</label>
    <input name="username" required autocomplete="username">
  </div>
  <div>
    <label>Password</label>
    <input type="password" name="password" required autocomplete="current-password">
  </div>
  <div>
    <label>Role</label>
    <select name="role" required>
      <option value="" disabled selected>Select role…</option>
      <option value="producer">Producer</option>
      <option value="supplier">Supplier</option>
      <option value="consumer">Consumer</option>
      <option value="admin">Admin</option>
    </select>
  </div>
  <button type="submit">Sign in</button>
</form>
<p class="muted">Tip: default admin → <b>admin / admin123</b></p>
<?php render_footer(); ?>
