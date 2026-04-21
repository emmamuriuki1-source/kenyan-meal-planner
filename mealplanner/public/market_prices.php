<?php
ob_start();
session_start();
define('APP_PATH', dirname(__DIR__) . '/app');
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/helpers.php';
requireLogin();

$uid     = $_SESSION['user_id'];
$success = '';
$error   = '';

// Save / update price
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $price     = (float)($_POST['price_per_unit'] ?? 0);
    $unit      = trim($_POST['unit'] ?? '');

    if ($item_name && $price > 0) {
        $check = $conn->prepare("SELECT id FROM market_prices WHERE user_id=? AND item_name=?");
        $check->bind_param('is', $uid, $item_name);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $stmt = $conn->prepare("UPDATE market_prices SET price_per_unit=?, unit=? WHERE id=?");
            $stmt->bind_param('dsi', $price, $unit, $existing['id']);
        } else {
            $stmt = $conn->prepare("INSERT INTO market_prices (user_id, item_name, price_per_unit, unit) VALUES (?,?,?,?)");
            $stmt->bind_param('isds', $uid, $item_name, $price, $unit);
        }
        $stmt->execute();
        $success = "Price for '$item_name' saved.";
    } else {
        $error = 'Item name and price are required.';
    }
}

// Delete
if (isset($_GET['delete'])) {
    $pid  = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM market_prices WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $pid, $uid);
    $stmt->execute();
    header('Location: market_prices.php');
    exit;
}

$prices = $conn->query("SELECT * FROM market_prices WHERE user_id=$uid ORDER BY item_name");

// Common Kenyan items for quick-add suggestions
$suggestions = [
    'Maize Flour' => ['price' => 130, 'unit' => '2kg bag'],
    'Rice'        => ['price' => 160, 'unit' => 'kg'],
    'Beans'       => ['price' => 120, 'unit' => 'kg'],
    'Sukuma Wiki' => ['price' => 20,  'unit' => 'bunch'],
    'Tomatoes'    => ['price' => 50,  'unit' => 'kg'],
    'Onions'      => ['price' => 60,  'unit' => 'kg'],
    'Cooking Oil' => ['price' => 200, 'unit' => '500ml'],
    'Wheat Flour' => ['price' => 120, 'unit' => '2kg bag'],
    'Sugar'       => ['price' => 130, 'unit' => 'kg'],
    'Salt'        => ['price' => 30,  'unit' => '500g'],
    'Milk'        => ['price' => 60,  'unit' => 'litre'],
    'Eggs'        => ['price' => 15,  'unit' => 'piece'],
    'Ndengu'      => ['price' => 100, 'unit' => 'kg'],
    'Potatoes'    => ['price' => 50,  'unit' => 'kg'],
    'Cabbage'     => ['price' => 40,  'unit' => 'head'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Prices â€“ MealPlanner KE</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include APP_PATH . '/includes/navbar.php'; ?>

<div class="container">
    <h1 class="page-title">ðŸ›’ Market Prices</h1>
    <p style="color:#666; margin-bottom:20px;">Enter current local market prices to get accurate budget estimates.</p>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="grid-2">
        <!-- Add Price Form -->
        <div class="card">
            <div class="card-title">Add / Update Price</div>
            <form method="POST">
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="item_name" id="itemNameInput" required placeholder="e.g. Maize Flour" list="item-suggestions">
                    <datalist id="item-suggestions">
                        <?php foreach ($suggestions as $name => $data): ?>
                            <option value="<?= e($name) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Price (KES)</label>
                        <input type="number" name="price_per_unit" id="priceInput" required step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <input type="text" name="unit" id="unitInput" placeholder="kg, bunch, litre...">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Save Price</button>
            </form>
        </div>

        <!-- Quick Add Suggestions -->
        <div class="card">
            <div class="card-title">Common Kenyan Items (click to fill)</div>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <?php foreach ($suggestions as $name => $data): ?>
                    <button class="btn btn-outline btn-sm"
                        onclick="fillForm('<?= e($name) ?>', <?= $data['price'] ?>, '<?= e($data['unit']) ?>')">
                        <?= e($name) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Prices Table -->
    <div class="card">
        <div class="card-title">Your Saved Prices</div>
        <?php if ($prices->num_rows > 0): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price (KES)</th>
                        <th>Unit</th>
                        <th>Last Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $prices->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($p['item_name']) ?></td>
                        <td><?= number_format($p['price_per_unit'], 2) ?></td>
                        <td><?= e($p['unit']) ?></td>
                        <td><?= date('d M Y', strtotime($p['updated_at'])) ?></td>
                        <td>
                            <button class="btn btn-outline btn-sm"
                                onclick="fillForm('<?= e($p['item_name']) ?>', <?= $p['price_per_unit'] ?>, '<?= e($p['unit']) ?>')">
                                Edit
                            </button>
                            <a href="market_prices.php?delete=<?= $p['id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete this price?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="alert alert-info">No prices saved yet. Add some above or click a quick-add button.</div>
        <?php endif; ?>
    </div>
</div>

<footer>MealPlanner KE &copy; <?= date('Y') ?></footer>

<script>
function fillForm(name, price, unit) {
    document.getElementById('itemNameInput').value = name;
    document.getElementById('priceInput').value    = price;
    document.getElementById('unitInput').value     = unit;
    document.getElementById('itemNameInput').focus();
}
</script>
</body>
</html>
