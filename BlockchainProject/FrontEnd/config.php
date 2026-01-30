<?php
// Shared config + helpers
session_start();

const USERS_FILE    = __DIR__ . '/users.txt';
const PRODUCTS_FILE = __DIR__ . '/products.txt';

$ALLOWED_ROLES = ['producer','supplier','consumer','admin'];

// Ensure files
if (!file_exists(USERS_FILE))    file_put_contents(USERS_FILE, "");
if (!file_exists(PRODUCTS_FILE)) file_put_contents(PRODUCTS_FILE, "");

// Seed default admin if empty (no wallet for admin by default)
if (filesize(USERS_FILE) === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    // username|hash|role|address|privkey  (empty addr/pk for admin)
    file_put_contents(USERS_FILE, "admin|$hash|admin||\n", FILE_APPEND);
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function require_login(): void {
    if (!isset($_SESSION['user'])) {
        header("Location: login.php");
        exit;
    }
}
function require_role(string|array $roles): void {
    require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['user']['role'] ?? '', $roles, true)) {
        header("Location: dashboard.php?error=" . urlencode("Access denied for this role."));
        exit;
    }
}

function render_header(string $title = "App") {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>'.h($title).'</title>';
    echo '<style>
        :root { --bg:#0f172a; --muted:#94a3b8; --text:#e5e7eb; --accent:#22d3ee; --accent2:#a78bfa; }
        *{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Segoe UI,Arial,sans-serif;background:linear-gradient(135deg,#0f172a,#1f2937);color:var(--text)}
        .wrap{min-height:100svh;display:grid;place-items:center;padding:24px}
        .card{width:100%;max-width:980px;background:rgba(17,24,39,.85);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.06);border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
        h1{margin:0 0 10px;font-size:26px} p.sub{margin:0 0 20px;color:var(--muted)}
        form{display:grid;gap:12px;margin-top:6px}
        label{font-size:14px;color:#cbd5e1}
        input,select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.08);background:#0b1220;color:var(--text)}
        button{padding:10px 14px;border-radius:10px;border:0;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#0b1220;font-weight:700;cursor:pointer}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .muted{color:var(--muted);font-size:13px}
        .bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
        .tag{display:inline-block;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.08);font-size:12px;color:#cbd5e1}
        .msg{margin:8px 0 0;color:#fca5a5} .ok{color:#86efac}
        table{width:100%;border-collapse:collapse;margin-top:14px}
        td,th{padding:8px;border-bottom:1px dashed rgba(255,255,255,.08);font-size:14px}
        .logout{background:#ef4444;color:white}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        @media(min-width:900px){.grid2{grid-template-columns:1fr 1fr}}
    </style></head><body><div class="wrap"><div class="card">';
}
function render_footer(){ echo '</div></div></body></html>'; }

/** USERS
 * Line format: username|password_hash|role|eth_address|eth_privkey
 */
function get_users(): array {
    $users = [];
    $lines = @file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        // Backward compatibility: accept 3 or 5 columns
        if (count($parts) === 3) {
            [$u,$h,$r] = $parts;
            $users[$u] = ['hash'=>$h, 'role'=>$r, 'eth_address'=>'', 'eth_privkey'=>''];
        } elseif (count($parts) >= 5) {
            [$u,$h,$r,$addr,$pk] = $parts;
            $users[$u] = ['hash'=>$h, 'role'=>$r, 'eth_address'=>$addr, 'eth_privkey'=>$pk];
        }
    }
    return $users;
}

function user_exists(string $username): bool {
    $users = get_users();
    return isset($users[$username]);
}

function add_user(string $username, string $password, string $role, string $eth_address = '', string $eth_privkey = ''): bool {
    global $ALLOWED_ROLES;
    $username = trim($username);
    if ($username === '' || $password === '' || !in_array($role, $ALLOWED_ROLES, true)) return false;
    if (user_exists($username)) return false;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $line = implode('|', [$username, $hash, $role, $eth_address, $eth_privkey]) . "\n";
    return (bool) file_put_contents(USERS_FILE, $line, FILE_APPEND);
}

/** PRODUCTS helpers unchanged (if you’re using them) */
function get_products(): array {
    if (!file_exists(PRODUCTS_FILE)) return [];
    $rows = [];
    $lines = @file(PRODUCTS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (count($parts) === 11) {
            [$id,$owner,$supplier,$consumer, $ownertx,$suppliertx,$name,$price,$qty,$status,$updated] = $parts;
            $rows[(int)$id] = [
                'id'=>(int)$id,'owner'=>$owner,
                'supplier'=>$supplier,
                'consumer'=>$consumer,
                'ownertx'=>$ownertx,
                'suppliertx'=>$suppliertx,
                'name'=>$name,
                'price'=>(float)$price,'qty'=>(int)$qty,'status'=>$status,'updated_at'=>$updated
            ];
        }
    }
    ksort($rows);
    return $rows;
}
function save_products(array $rows): bool {
    $buf = '';
    foreach ($rows as $r) {
        $buf .= implode('|', [$r['id'],$r['owner'],$r['supplier'],$r['consumer'],$r['ownertx'],$r['suppliertx'],$r['name'],$r['price'],$r['qty'],$r['status'],$r['updated_at']]) . "\n";
    }
    return (bool)file_put_contents(PRODUCTS_FILE, $buf);
}


function next_product_id(array $rows): int {
    return empty($rows) ? 1 : (max(array_keys($rows)) + 1);
}

function now_iso(): string {
    // Basic ISO-ish timestamp (no timezone conversion)
    return date('Y-m-d\TH:i:s');
}
function update_product_qty(int $productId, int $newQty): bool {
    $rows = get_products();
    if (!isset($rows[$productId])) return false;
    if ($newQty < 0) return false;
    $rows[$productId]['qty'] = $newQty;
    $rows[$productId]['updated_at'] = now_iso();
    return save_products($rows);
}

/** Deduct $delta (positive) from product qty; returns new qty or -1 on failure */
function deduct_product_qty(int $productId, int $delta): int {
    $rows = get_products();
    if (!isset($rows[$productId])) return -1;
    if ($delta <= 0) return -1;
    
    $cur = (int)$rows[$productId]['qty'];
    if ($delta > $cur) return -1;
    
    $rows[$productId]['qty'] = $cur - $delta;
    // echo "test:".$delta." : ".$cur;
    $rows[$productId]['updated_at'] = now_iso();
    if (!save_products($rows)) return -1;
    return $rows[$productId]['qty'];
}

/** Generate QR code URL containing product blockchain data */
function generate_qr_code(int $productId, string $productName, string $ownertx = '', string $suppliertx = ''): string {
    // Encode product data: id, name, owner transaction, supplier transaction
    $data = json_encode([
        'id' => $productId,
        'name' => $productName,
        'ownertx' => $ownertx,
        'suppliertx' => $suppliertx,
        'timestamp' => now_iso()
    ]);
    
    // Use QR Server API to generate QR code
    $encoded = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=$encoded";
}

/** Get product history from blockchain (simulated with local data) */
function get_product_history(int $productId): array {
    $all = get_products();
    if (!isset($all[$productId])) {
        return ['error' => 'Product not found'];
    }
    
    $product = $all[$productId];
    return [
        'id' => $product['id'],
        'name' => $product['name'],
        'price' => $product['price'],
        'quantity' => $product['qty'],
        'status' => $product['status'],
        'owner' => $product['owner'],
        'owner_tx' => $product['ownertx'],
        'supplier' => $product['supplier'],
        'supplier_tx' => $product['suppliertx'],
        'consumer' => $product['consumer'],
        'created_at' => $product['updated_at'],
        'updated_at' => $product['updated_at']
    ];
}

