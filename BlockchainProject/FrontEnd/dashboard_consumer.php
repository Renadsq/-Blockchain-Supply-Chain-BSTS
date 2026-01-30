<?php
require __DIR__ . '/config.php';
require_role('consumer');

$me = $_SESSION['user'];

// Load products
$all = get_products();
$available = array_filter($all, fn($r) => ($r['status'] ?? '') === 'supplied' && (int)($r['qty'] ?? 0) > 0);
$mine = array_filter($all, fn($r) => ($r['consumer'] ?? '') === $me['username'] && ($r['status'] ?? '') === 'consumed');
$msg = '';

// Search filter for available products
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $qLower = mb_strtolower($q);
    $available = array_filter($available, function($r) use ($qLower){
        return str_contains(mb_strtolower($r['name']), $qLower) ||
               str_contains(mb_strtolower($r['owner']), $qLower);
    });
}

// Search filter for my products
$q2 = trim($_GET['q2'] ?? '');
if ($q2 !== '') {
    $q2Lower = mb_strtolower($q2);
    $mine = array_filter($mine, function($r) use ($q2Lower){
        return str_contains(strtolower((string)$r['id']), $q2Lower) ||
               str_contains(mb_strtolower($r['name']), $q2Lower) ||
               str_contains(mb_strtolower($r['ownertx']), $q2Lower);
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$action = $_POST['action'] ?? '';
		$all = get_products(); // reload fresh

		if ($action === 'receive') {
				$id = (int)($_POST['id'] ?? 0);
				$qty = (int)($_POST['qty'] ?? 0);
				$txh = trim($_POST['txhash'] ?? '');

				if ($id && isset($all[$id]) && $qty > 0 && (int)$all[$id]['qty'] >= $qty) {
						// Update the existing supplied product: set consumer and status to consumed
						$all[$id]['consumer'] = $me['username'];
						$all[$id]['qty'] = $qty;
						$all[$id]['ownertx'] = $txh;
						$all[$id]['status'] = 'consumed';
						$all[$id]['updated_at'] = now_iso();
						$msg = save_products($all) ? "Product received." : "Failed to record receipt.";
				} else {
						$msg = "Invalid request or insufficient quantity.";
				}
		}

	// Refresh lists
	$all = get_products();
	$available = array_filter($all, fn($r) => ($r['status'] ?? '') === 'supplied' && (int)($r['qty'] ?? 0) > 0);
	$mine = array_filter($all, fn($r) => ($r['consumer'] ?? '') === $me['username'] && ($r['status'] ?? '') === 'consumed');
}

render_header("Consumer Dashboard");
?>
<div class="bar">
	<div>
		<h1>Consumer Dashboard</h1>
		<span class="tag"><?= h($me['role']) ?></span>
	</div>
	<div>
		<a href="login.php" class="btn-secondary" style="margin-right:8px;text-decoration:none;"><button class="btn-secondary">Home</button></a>
		<a href="logout.php"><button class="btn-danger">Log out</button></a>
	</div>
</div>

<p class="sub">Welcome, <b><?= h($me['username']) ?></b>. View approved products, create shipments. On-chain actions record in Sepolia.</p>
<?php if ($msg): ?>
	<div class="<?= str_contains(strtolower($msg),'fail') ? 'msg' : 'ok' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<?php
// Balance panel
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

<p></p>

<!-- Search -->
<form method="get" action="dashboard_consumer.php" style="display:flex; gap:10px; margin: 10px 0 16px;">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by product or producer…" style="flex:1">
  <button type="submit">Search</button>
</form>

<p class="sub">Search approved products and purchase your preferred quantity.</p>
<?php if ($msg): ?>
  <div class="<?= str_contains(strtolower($msg),'fail') ? 'msg' : 'ok' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<h2 style="margin:20px 0 8px;">Available Products</h2>
<table>
	<tr>
		<th>ID</th><th>Product</th><th>Producer</th><th>Price</th><th>Available</th><th>Purchase</th>
	</tr>
	<?php if (empty($available)): ?>
		<tr><td colspan="6" class="muted">No approved products available.</td></tr>
	<?php else: ?>
		<?php foreach ($available as $p): ?>
			<tr data-id="<?= h($p['id']) ?>" data-name="<?= h($p['name']) ?>" data-price="<?= h($p['price']) ?>" data-qty="<?= h($p['qty']) ?>">
				<td>#<?= h($p['id']) ?></td>
				<td><?= h($p['name']) ?></td>
				<td><?= h($p['owner']) ?></td>
				<td><?= h(number_format($p['price'], 2)) ?></td>
				<td><?= h($p['qty']) ?></td>
				<td>
					<form method="post" action="dashboard_consumer.php" class="receive-form" style="display:flex;gap:8px;align-items:center;">
						<input type="hidden" name="action" value="receive">
						<input type="hidden" name="id" value="<?= h($p['id']) ?>">
						<input type="hidden" name="name" value="<?= h($p['name']) ?>">
						<input type="hidden" name="txhash" value="">
						<input type="number" name="qty" min="1" max="<?= h($p['qty']) ?>" value="<?= h($p['qty']) ?>" style="width:80px">
						<button type="button" class="btn-onchain-receive">Approve</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
	<?php endif; ?>
</table>

<h2 style="margin:24px 0 8px;">Your Products</h2>

<!-- Search -->
<form method="get" action="dashboard_consumer.php" style="display:flex; gap:10px; margin: 10px 0 16px;">
  <input type="text" name="q2" value="<?= h($_GET['q2'] ?? '') ?>" placeholder="Search by productID or Transaction…" style="flex:1">
  <button type="submit">Search</button>
</form>

<p class="sub">Search approved products and purchase your preferred quantity.</p>

<table>
	<?php if (empty($mine)): ?>
		<tr><td colspan="7" class="muted">No products yet.</td></tr>
	<?php else: ?>
		<tr>
		<th>ID</th><th>Product</th><th>Price</th><th>Qty</th><th>Status</th><th>Updated</th><th>Transaction</th><th>QR Code</th>
	</tr>
		<?php foreach ($mine as $r): ?>
		<tr data-id="<?= h($r['id']) ?>" data-name="<?= h($r['name']) ?>" data-price="<?= h($r['price']) ?>" data-qty="<?= h($r['qty']) ?>">
			<td>#<?= h($r['id']) ?></td>
			<td><?= h($r['name']) ?></td>
			<td><?= h(number_format($r['price'], 2)) ?></td>
			<td><?= h($r['qty']) ?></td>
			<td>
				<span class="pill <?= $r['status']==='consumed'?'ok':'pending' ?>">
					<?= h($r['status']) ?>
				</span>
			</td>
			<td class="muted"><?= h($r['updated_at']) ?></td>
			<td class="muted">
				<a href="https://sepolia.etherscan.io/tx/<?= htmlspecialchars(h($r['ownertx'])) ?>" target="_blank">View</a>
			</td>
			<td style="text-align:center;">
				<img src="<?= generate_qr_code($r['id'], $r['name'], $r['ownertx'], $r['suppliertx']) ?>" alt="QR" width="60" height="60" data-qr-product="<?= h($r['id']) ?>" data-qr-name="<?= h($r['name']) ?>" style="border:1px solid #444;padding:2px;cursor:pointer;">
			</td>
		</tr>
		<?php endforeach; ?>
	<?php endif; ?>
</table><p class="muted" style="margin-top:10px;">Only your own received products are shown here (consumer = <?= h($me['username']) ?>).</p>

<?php render_footer(); ?>


<!-- ====== Ethers.js (UMD build) & on-chain glue ====== -->
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>
<script src="contract.js" defer></script>

<script>
// Consumer receive: call registerProduct on-chain to record consumption
async function handleReceiveOnChain(row) {
	const id = row.dataset.id;
	const name = row.dataset.name;
	
	// Simple meta payload: mark as consumed by this consumer
	const metaHash = `consumed:${id}|${name}`;
	
	const { contract } = await getSignerAndContract();
	// Call registerProduct to record the consumption on-chain
	const tx = await contract.registerProduct(BigInt(id), metaHash);
	const receipt = await tx.wait();
	return receipt?.hash || tx.hash;
}

function wireReceiveButtons(){
	const buttons = document.querySelectorAll('.btn-onchain-receive');
	buttons.forEach(btn => {
		btn.addEventListener('click', async () => {
			const row = btn.closest('tr');
			const form = row.querySelector('form.receive-form');
			const txInput = form.querySelector('input[name="txhash"]');
			btn.disabled = true;
			btn.textContent = 'Approving…';
			try {
				const txhash = await handleReceiveOnChain(row);
				txInput.value = txhash || '';
				form.submit();
			} catch (err) {
				console.error(err);
				alert('On-chain approval failed: ' + (err?.shortMessage || err?.message || err));
				btn.disabled = false;
				btn.textContent = 'Approve';
			}
		});
	});
}

window.addEventListener('load', wireReceiveButtons);
</script>

<!-- ====== Ethers.js (UMD build) & on-chain glue ====== -->
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>
<script src="contract.js" defer></script>
<script src="qr_modal.js" defer></script>

