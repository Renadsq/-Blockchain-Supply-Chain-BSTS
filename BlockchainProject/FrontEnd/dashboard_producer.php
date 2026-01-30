<?php
require __DIR__ . '/config.php';
require_role('producer');

$me = $_SESSION['user'];
$all = get_products();
$mine = array_filter($all, fn($r) => $r['owner'] === $me['username']);
$msg = '';

// ---- Handle actions: add / update / delete / approve ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $all = get_products(); // reload fresh

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $qty   = (int)($_POST['qty'] ?? 0);
        if ($name === '' || $price < 0 || $qty < 0) {
            $msg = "Please provide valid name, price ≥ 0, quantity ≥ 0.";
        } else {
            $id = next_product_id($all);

            $all[$id] = [
                'id'=>$id,
                'owner'=>$me['username'],
                'supplier'=>$me['username'],
                'consumer'=>$me['username'],
                'ownertx'=>$me['username'],
                'suppliertx'=>$me['username'],
                'name'=>$name,
                'price'=>$price,
                'qty'=>$qty,
                'status'=>'pending',
                'updated_at'=>now_iso()
            ];
            $msg = save_products($all) ? "Product added." : "Failed to add product.";
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && isset($all[$id]) && $all[$id]['owner'] === $me['username']) {
            //  If approved, block edits (defense in depth)
            if (($all[$id]['status'] ?? '') === 'approved') {
                $msg = "Product #$id is approved and cannot be edited.";
            } else {
                $name = trim($_POST['name'] ?? $all[$id]['name']);
                $price = (float)($_POST['price'] ?? $all[$id]['price']);
                $qty   = (int)($_POST['qty'] ?? $all[$id]['qty']);
                if ($name === '' || $price < 0 || $qty < 0) {
                    $msg = "Please provide valid name, price ≥ 0, quantity ≥ 0.";
                } else {
                    $all[$id]['name']  = $name;
                    $all[$id]['price'] = $price;
                    $all[$id]['qty']   = $qty;
                    $all[$id]['updated_at'] = now_iso();
                    $msg = save_products($all) ? "Product #$id updated." : "Failed to update.";
                }
            }
        } else {
            $msg = "Not found or not your product.";
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && isset($all[$id]) && $all[$id]['owner'] === $me['username']) {
            // If approved, block deletes
            if (($all[$id]['status'] ?? '') === 'approved') {
                $msg = "Product #$id is approved and cannot be deleted.";
            } else {
                unset($all[$id]);
                $msg = save_products($all) ? "Product #$id deleted." : "Failed to delete.";
            }
        } else {
            $msg = "Not found or not your product.";
        }
    }

    if ($action === 'approve') {
        $id = (int)($_POST['id'] ?? 0);
        $txhash = trim($_POST['txhash'] ?? ''); // comes from JS after on-chain success
        if ($id && isset($all[$id]) && $all[$id]['owner'] === $me['username']) {
            // Only allow pending -> approved here; unapprove remains allowed but doesn't require chain
            if (($all[$id]['status'] ?? '') !== 'approved') {
                // set approved
                $all[$id]['ownertx'] = $txhash;
                $all[$id]['status'] = 'approved';
                $all[$id]['updated_at'] = now_iso();
                $saved = save_products($all);

                if ($saved) {
                    // Show tx hash if provided
                    if ($txhash !== '') {
                        $short = substr($txhash, 0, 10) . '…';
                        $msg = "Product #$id approved. On-chain tx: $short";
                    } else {
                        $msg = "Product #$id approved.";
                    }
                } else {
                    $msg = "Failed to update status.";
                }
            } else {
                // allow unapprove (no chain write)
                $all[$id]['status'] = 'pending';
                $all[$id]['updated_at'] = now_iso();
                $msg = save_products($all) ? "Product #$id marked pending." : "Failed to update status.";
            }
        } else {
            $msg = "Not found or not your product.";
        }
    }

    // Refresh filtered list after changes
    $mine = array_filter($all, fn($r) => $r['owner'] === $me['username']);
}

render_header("Producer Dashboard");
?>
<div class="bar">
  <div>
    <h1>Producer Dashboard</h1>
    <span class="tag"><?= h($me['role']) ?></span>
  </div>
  <div>
    <a href="login.php" class="btn-secondary" style="margin-right:8px;text-decoration:none;"><button class="btn-secondary">Home</button></a>
    <a href="logout.php"><button class="btn-danger">Log out</button></a>
  </div>
</div>

<p class="sub">Welcome, <b><?= h($me['username']) ?></b>. Manage your products below.</p>
<?php if ($msg): ?>
  <div class="<?= str_contains($msg,'fail') || str_contains(strtolower($msg),'not') ? 'msg' : 'ok' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<?php
// Balance panel: show user's stored ETH address if available
$users = get_users();
$ethAddr = $users[$me['username']]['eth_address'] ?? '';
// Get list of other users for transfer
$otherUsers = array_filter($users, fn($u, $name) => $name !== $me['username'], ARRAY_FILTER_USE_BOTH);
?>

<!-- Pass user ETH address to JavaScript -->
<script>
  const USER_ETH_ADDRESS = '<?= h($ethAddr) ?>';
  if (!USER_ETH_ADDRESS) {
    console.warn('Warning: User ETH address not set. Visit admin dashboard to assign a wallet.');
  }
</script>

<!-- Balance Panel Card - matches overall dashboard layout -->
<div style="margin:16px 0;padding:16px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:12px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <div>
      <h3 style="margin:0 0 4px;font-size:16px;color:#e5e7eb;">💰 Internal Balance</h3>
      <p style="margin:0;font-size:13px;color:#94a3b8;">ETH Address: <code style="font-size:11px;color:#cbd5e1;background:rgba(0,0,0,.2);padding:2px 6px;border-radius:4px;"><?= h(substr($ethAddr, 0, 10)) ?>…<?= h(substr($ethAddr, -8)) ?></code></p>
    </div>
  </div>
  
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-bottom:12px;">
    <!-- Show Balance Button -->
    <button id="btn-show-balance" style="padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(34,211,238,.15);color:#22d3ee;font-weight:600;font-size:13px;cursor:pointer;transition:all .2s;">
      📊 Show Balance
    </button>
    
    <!-- Add Credits (Deposit) Button -->
    <button id="btn-add-credits" style="padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(168,85,247,.15);color:#a78bfa;font-weight:600;font-size:13px;cursor:pointer;transition:all .2s;">
      ➕ Add Credits
    </button>
  </div>

  <!-- Transfer Panel -->
  <div style="padding:12px;background:rgba(17,24,39,.8);border-radius:8px;border:1px dashed rgba(255,255,255,.06);">
    <p style="margin:0 0 10px;font-size:12px;color:#94a3b8;">Send to another user:</p>
    <div style="display:flex;gap:8px;align-items:center;">
      <select id="transfer-recipient" style="flex:1;padding:10px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.12);background:#0b1220;color:#e5e7eb;font-size:13px;">
        <option value="">Select recipient…</option>
        <?php foreach ($otherUsers as $username => $info): ?>
          <option value="<?= h($info['eth_address']) ?>" data-username="<?= h($username) ?>">
            <?= h($username) ?> (<?= h(substr($info['eth_address'], 0, 6)) ?>…)
          </option>
        <?php endforeach; ?>
      </select>
      <button id="btn-balance-transfer" style="padding:10px 14px;border-radius:8px;border:0;background:linear-gradient(135deg,#22d3ee,#a78bfa);color:#0b1220;font-weight:600;font-size:13px;cursor:pointer;white-space:nowrap;">
        💸 Pay
      </button>
    </div>
  </div>
</div>

<!-- Add product -->
<h2 style="margin:20px 0 8px;">Add Product</h2>
<form method="post" action="dashboard_producer.php">
  <input type="hidden" name="action" value="add">
  <div class="row2">
    <div>
      <label>Name</label>
      <input name="name" required placeholder="e.g., Fresh Apples">
    </div>
    <div>
      <label>Price</label>
      <input name="price" type="number" step="0.01" min="0" required placeholder="e.g., 12.50">
    </div>
  </div>
  <div class="row2">
    <div>
      <label>Quantity</label>
      <input name="qty" type="number" step="1" min="0" required placeholder="e.g., 100">
    </div>
  </div>
  <button type="submit">Add</button>
  <p class="muted">New products start as <b>pending</b>. Approve to publish on chain.</p>
</form>

<!-- List / edit products -->
<h2 style="margin:24px 0 8px;">My New Products</h2>
<table>
  <?php if (empty($mine)): ?>
    <tr><td colspan="7" class="muted">No products yet.</td></tr>
  <?php else: ?>
  <tr>
    <th>ID</th><th>Name</th><th>Price</th><th>Qty</th><th>Status</th><th>Updated</th><th>Actions</th>
  </tr>
    <?php foreach ($mine as $r): ?>
    <?php if ($r['status'] === 'pending'): ?>
      <tr data-id="<?= h($r['id']) ?>"
          data-name="<?= h($r['name']) ?>"
          data-price="<?= h($r['price']) ?>"
          data-qty="<?= h($r['qty']) ?>">
        <td>#<?= h($r['id']) ?></td>
        <td>
          <?php if ($r['status'] !== 'pending'): ?>
            <!-- Approved: read-only -->
            <?= h($r['name']) ?>
          <?php else: ?>
            <!-- Editable only if NOT approved -->
            <form method="post" action="dashboard_producer.php" style="display:flex;gap:8px;align-items:center;">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= h($r['id']) ?>">
              <input name="name" value="<?= h($r['name']) ?>" style="max-width:220px">
              <input name="price" type="number" step="0.01" min="0" value="<?= h($r['price']) ?>" style="width:110px">
              <input name="qty" type="number" step="1" min="0" value="<?= h($r['qty']) ?>" style="width:90px">
               <?php if ($r['status']==='pending'): ?>
                     <button type="submit">Save</button>
                <?php endif; ?>
            </form>
          <?php endif; ?>
        </td>
        <td><?= h(number_format($r['price'], 2)) ?></td>
        <td><?= h($r['qty']) ?></td>
        <td>
          <span class="pill <?= $r['status']==='approved'?'ok':'pending' ?>">
            <?= h($r['status']) ?>
          </span>
        </td>
        <td class="muted"><?= h($r['updated_at']) ?></td>
        <td class="actions">
          <!-- Approve / Unapprove -->
          <form method="post" action="dashboard_producer.php" class="approve-form">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= h($r['id']) ?>">
            <!-- JS will fill txhash only after chain success -->
            <input type="hidden" name="txhash" value="">
            <?php if ($r['status']==='pending'): ?>
              <!-- IMPORTANT: this button triggers on-chain call first -->
              <button type="button" class="btn-onchain-approve">Approve </button>
            <?php endif; ?>
          </form>

          <!-- Delete: only if NOT approved -->
          <?php if ($r['status'] === 'pending'): ?>
            <form method="post" action="dashboard_producer.php" onsubmit="return confirm('Delete product #<?= h($r['id']) ?>?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= h($r['id']) ?>">
              <button type="submit" class="btn-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</table>

<style>
div.tablecontainer {
  overflow-x: auto;
}

table {
  border-collapse: collapse;
  width: 100%;
}

table, th, td {
   border: 1px solid #ddd;
   padding: 8px;
   text-align: left;
}
</style>
<h2 style="margin:24px 0 8px;">My Approved Products</h2>
<table>
  <?php if (empty($mine)): ?>
    <tr><td colspan="7" class="muted">No products yet.</td></tr>
  <?php else: ?>
    <tr>
    <th>ID</th><th>Name</th><th>Price</th><th>Qty</th><th>Status</th><th>Updated</th><th>Transaction</th><th>QR Code</th>
  </tr>
    <?php foreach ($mine as $r): ?>
    <?php if ($r['status'] === 'approved'): ?>
      <tr data-id="<?= h($r['id']) ?>"
          data-name="<?= h($r['name']) ?>"
          data-price="<?= h($r['price']) ?>"
          data-qty="<?= h($r['qty']) ?>">
        <td>#<?= h($r['id']) ?></td>
        <td>
          <?php if ($r['status'] === 'approved'): ?>
            <!-- Approved: read-only -->
            <?= h($r['name']) ?>
          <?php endif; ?>
        </td>
        <td><?= h(number_format($r['price'], 2)) ?></td>
        <td><?= h($r['qty']) ?></td>
        <td>
          <span class="pill <?= $r['status']==='approved'?'ok':'pending' ?>">
            <?= h($r['status']) ?>
          </span>
        </td>
        <td class="muted"><?= h($r['updated_at']) ?></td>
        <td class="muted">
            <!--$url = "https://sepolia.etherscan.io/tx/" . h($r['history1']);-->
            <a href="https://sepolia.etherscan.io/tx/<?= htmlspecialchars(h($r['ownertx'])) ?>" target="_blank">
                View </a>        
            </td>
        <td style="text-align:center;">
            <img src="<?= generate_qr_code($r['id'], $r['name'], $r['ownertx'], '') ?>" alt="QR" width="60" height="60" data-qr-product="<?= h($r['id']) ?>" data-qr-name="<?= h($r['name']) ?>" style="border:1px solid #444;padding:2px;cursor:pointer;">
        </td>
      </tr>
        <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</table>

<p class="muted" style="margin-top:10px;">Only your own products are shown here (owner = <?= h($me['username']) ?>).</p>

<?php render_footer(); ?>

<!-- ====== Ethers.js (UMD build) & on-chain glue ====== -->
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>
<script src="contract.js" defer></script>
<script src="qr_modal.js" defer></script>

