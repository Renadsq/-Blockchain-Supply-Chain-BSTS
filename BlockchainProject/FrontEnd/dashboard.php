<?php
require __DIR__ . '/config.php';
require_login();

$role = $_SESSION['user']['role'] ?? '';
switch ($role) {
  case 'admin':
    header("Location: dashboard_admin.php");
    break;
  case 'producer':
    header("Location: dashboard_producer.php");
    break;
  case 'supplier':
    header("Location: dashboard_supplier.php");
    break;
  case 'consumer':
    header("Location: dashboard_consumer.php");
    break;
  default:
    // Fallback: show a tiny page
    render_header("Dashboard");
    echo '<div class="bar"><div><h1>Dashboard</h1><span class="tag">'.h($role ?: 'unknown').'</span></div>
          <div><a href="logout.php"><button class="logout">Log out</button></a></div></div>';
    echo '<p class="sub">Unknown role. Please contact admin.</p>';
    render_footer();
}
exit;
