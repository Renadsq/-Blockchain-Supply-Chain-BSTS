<?php
require __DIR__ . '/config.php';
require_role('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $nu = trim($_POST['new_username'] ?? '');
    $np = $_POST['new_password'] ?? '';
    $nr = $_POST['new_role'] ?? '';
    $addr = trim($_POST['eth_address'] ?? '');
    $pk   = trim($_POST['eth_privkey'] ?? '');

    if ($nu === '' || $np === '' || !in_array($nr, $ALLOWED_ROLES, true)) {
        $msg = "Please provide username, password, and a valid role.";
    } elseif (user_exists($nu)) {
        $msg = "User '$nu' already exists.";
    } else {
        $ok = add_user($nu, $np, $nr, $addr, $pk);
        $msg = $ok ? "User '$nu' created successfully." : "Failed to create user.";
    }
}

$me = $_SESSION['user'];
$users = get_users();

render_header("Admin Dashboard");
?>
<div class="bar">
  <div>
    <h1>Admin Dashboard</h1>
    <span class="tag"><?= h($me['role']) ?></span>
  </div>
  <div>
    <a href="logout.php"><button class="logout">Log out</button></a>
  </div>
</div>

<p class="sub">Welcome, <b><?= h($me['username']) ?></b>. Manage users below. On creation, a Sepolia wallet (address/private key) can be generated.</p>
<?php if ($msg): ?>
  <div class="<?= str_contains($msg,'successfully') ? 'ok' : 'msg' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="grid2">
  <div>
    <h2 style="margin:0 0 8px;">Existing Users</h2>
    <table>
      <tr><th>Username</th><th>Role</th><th>ETH Address</th></tr>
      <?php foreach ($users as $u => $info): ?>
        <tr>
          <td><?= h($u) ?></td>
          <td><?= h($info['role']) ?></td>
          <td><?= h($info['eth_address']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p class="muted">Private keys are stored server-side but not shown here. Handle with care.</p>
  </div>

  <div>
    <h2 style="margin:0 0 8px;">Create New User (with Sepolia Keypair)</h2>
    <form id="createForm" method="post" action="dashboard_admin.php">
      <input type="hidden" name="action" value="create">
      <div class="row">
        <div>
          <label>New Username</label>
          <input name="new_username" required>
        </div>
        <div>
          <label>New Password</label>
          <input type="password" name="new_password" required>
        </div>
      </div>
      <div class="row">
        <div>
          <label>Role</label>
          <select name="new_role" required>
            <option value="" disabled selected>Select role…</option>
            <option value="producer">Producer</option>
            <option value="supplier">Supplier</option>
            <option value="consumer">Consumer</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div>
          <label>Generate Keypair?</label>
          <select id="gen_wallet">
            <option value="yes" selected>Yes</option>
            <option value="no">No</option>
          </select>
        </div>
      </div>

      <!-- Hidden fields filled by JS when generating the wallet -->
      <input type="hidden" name="eth_address" id="eth_address">
      <input type="hidden" name="eth_privkey" id="eth_privkey">

      <button type="submit">Create User</button>
      <p class="muted">If "Yes", this will create an Ethereum keypair (address + private key) for Sepolia and store it with the user.</p>
      <p class="muted">You can later grant on-chain roles to this address in your smart contract.</p>
    </form>
  </div>
</div>

<?php render_footer(); ?>

<!-- Ethers v6 (browser) -->
<!-- put this BEFORE your <script> that uses ethers -->
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>


<script>
  window.addEventListener('load', () => {
    // attach the submit listener here so ethers is definitely available
    const form = document.getElementById('createForm');
    const genSel = document.getElementById('gen_wallet');
    const outAddr = document.getElementById('eth_address');
    const outPK   = document.getElementById('eth_privkey');

    form.addEventListener('submit', async (e) => {
      if (genSel.value === 'no') return;
      e.preventDefault();

      if (!window.ethers) { alert('ethers not loaded'); return; }

      const w = ethers.Wallet.createRandom();
      outAddr.value = w.address;
      outPK.value   = w.privateKey;
      form.submit();
    });
  });
</script>
