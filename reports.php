<?php
// ------------------------------
// ENABLE ERRORS
// ------------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------------
// DATABASE CONNECTION
// ------------------------------
include './config/connect.php';

function getPageNumber($key)
{
    if (!isset($_GET[$key])) {
        return 1;
    }
    $value = (int)$_GET[$key];
    return $value > 0 ? $value : 1;
}

function buildPageUrl($pageKey, $pageNumber)
{
    $params = $_GET;
    $params[$pageKey] = $pageNumber;
    return '?' . http_build_query($params);
}

function renderPagination($currentPage, $totalPages, $pageKey)
{
    if ($totalPages <= 1) {
        return;
    }
?>
    <div class="mt-4 flex items-center justify-between">
        <span class="text-xs text-gray-500">Page <?= $currentPage ?> of <?= $totalPages ?></span>
        <div class="flex items-center space-x-2">
            <?php if ($currentPage > 1): ?>
                <a href="<?= htmlspecialchars(buildPageUrl($pageKey, $currentPage - 1)) ?>" class="px-3 py-1 text-xs border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50">Previous</a>
            <?php endif; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= htmlspecialchars(buildPageUrl($pageKey, $currentPage + 1)) ?>" class="px-3 py-1 text-xs border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
<?php
}

// ------------------------------
// HANDLE DATE FILTERS
// ------------------------------
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$report_type = $_GET['report_type'] ?? 'movement';

// Get month name
$month_name = date('F', mktime(0, 0, 0, $month, 1));

// ------------------------------
// PAGINATION SETTINGS
// ------------------------------
$movements_per_page = 20;
$top_per_page = 5;
$low_stock_per_page = 8;

$movements_page = getPageNumber('movements_page');
$top_page = getPageNumber('top_page');
$low_stock_page = getPageNumber('low_stock_page');

$movements_offset = ($movements_page - 1) * $movements_per_page;
$top_offset = ($top_page - 1) * $top_per_page;
$low_stock_offset = ($low_stock_page - 1) * $low_stock_per_page;

// ------------------------------
// FETCH MONTHLY SUMMARY
// ------------------------------
$monthly_summary = mysqli_query($conn, "
    SELECT 
        COUNT(DISTINCT product_id) as products_moved,
        COALESCE(SUM(CASE WHEN change_type = 'IN' THEN quantity ELSE 0 END), 0) as total_in,
        COALESCE(SUM(CASE WHEN change_type = 'OUT' THEN quantity ELSE 0 END), 0) as total_out,
        COUNT(*) as total_transactions
    FROM stock_logs
    WHERE MONTH(created_at) = $month AND YEAR(created_at) = $year
");

$monthly_data = mysqli_fetch_assoc($monthly_summary);

// ------------------------------
// FETCH INVENTORY SUMMARY
// ------------------------------
$inventory_summary = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_products,
        COALESCE(SUM(quantity), 0) as total_stock,
        COALESCE(SUM(price * quantity), 0) as total_value,
        COALESCE(SUM(CASE WHEN quantity < 10 THEN 1 ELSE 0 END), 0) as low_stock
    FROM products
");

$inv_data = mysqli_fetch_assoc($inventory_summary);

// ------------------------------
// FETCH MOVEMENT REPORT
// ------------------------------
$movements = mysqli_query($conn, "
    SELECT 
        DATE(sl.created_at) as date,
        p.name as product_name,
        p.sku,
        sl.change_type,
        sl.quantity,
        sl.note
    FROM stock_logs sl
    JOIN products p ON sl.product_id = p.id
    WHERE MONTH(sl.created_at) = $month AND YEAR(sl.created_at) = $year
    ORDER BY sl.created_at DESC
    LIMIT $movements_per_page OFFSET $movements_offset
");

$movements_total = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count
    FROM stock_logs sl
    WHERE MONTH(sl.created_at) = $month AND YEAR(sl.created_at) = $year
"))['count'];
$movements_total_pages = max(1, (int)ceil($movements_total / $movements_per_page));
if ($movements_page > $movements_total_pages) {
    $movements_page = $movements_total_pages;
    $movements_offset = ($movements_page - 1) * $movements_per_page;
    $movements = mysqli_query($conn, "
        SELECT 
            DATE(sl.created_at) as date,
            p.name as product_name,
            p.sku,
            sl.change_type,
            sl.quantity,
            sl.note
        FROM stock_logs sl
        JOIN products p ON sl.product_id = p.id
        WHERE MONTH(sl.created_at) = $month AND YEAR(sl.created_at) = $year
        ORDER BY sl.created_at DESC
        LIMIT $movements_per_page OFFSET $movements_offset
    ");
}

// ------------------------------
// FETCH LOW STOCK PRODUCTS
// ------------------------------
$low_stock = mysqli_query($conn, "
    SELECT name, sku, quantity, price
    FROM products
    WHERE quantity < 10
    ORDER BY quantity ASC
    LIMIT $low_stock_per_page OFFSET $low_stock_offset
");

$low_stock_total = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count
    FROM products
    WHERE quantity < 10
"))['count'];
$low_stock_total_pages = max(1, (int)ceil($low_stock_total / $low_stock_per_page));
if ($low_stock_page > $low_stock_total_pages) {
    $low_stock_page = $low_stock_total_pages;
    $low_stock_offset = ($low_stock_page - 1) * $low_stock_per_page;
    $low_stock = mysqli_query($conn, "
        SELECT name, sku, quantity, price
        FROM products
        WHERE quantity < 10
        ORDER BY quantity ASC
        LIMIT $low_stock_per_page OFFSET $low_stock_offset
    ");
}

// ------------------------------
// FETCH TOP PRODUCTS
// ------------------------------
$top_products = mysqli_query($conn, "
    SELECT 
        p.name,
        p.sku,
        COALESCE(SUM(CASE WHEN sl.change_type = 'IN' THEN sl.quantity ELSE 0 END), 0) as total_in,
        COALESCE(SUM(CASE WHEN sl.change_type = 'OUT' THEN sl.quantity ELSE 0 END), 0) as total_out,
        COALESCE(SUM(sl.quantity), 0) as total_movement
    FROM products p
    LEFT JOIN stock_logs sl ON p.id = sl.product_id 
        AND MONTH(sl.created_at) = $month AND YEAR(sl.created_at) = $year
    GROUP BY p.id
    HAVING total_movement > 0
    ORDER BY total_movement DESC
    LIMIT $top_per_page OFFSET $top_offset
");

$top_total = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM (
        SELECT p.id
        FROM products p
        LEFT JOIN stock_logs sl ON p.id = sl.product_id 
            AND MONTH(sl.created_at) = $month AND YEAR(sl.created_at) = $year
        GROUP BY p.id
        HAVING COALESCE(SUM(sl.quantity), 0) > 0
    ) as top_groups
"))['count'];
$top_total_pages = max(1, (int)ceil($top_total / $top_per_page));
if ($top_page > $top_total_pages) {
    $top_page = $top_total_pages;
    $top_offset = ($top_page - 1) * $top_per_page;
    $top_products = mysqli_query($conn, "
        SELECT 
            p.name,
            p.sku,
            COALESCE(SUM(CASE WHEN sl.change_type = 'IN' THEN sl.quantity ELSE 0 END), 0) as total_in,
            COALESCE(SUM(CASE WHEN sl.change_type = 'OUT' THEN sl.quantity ELSE 0 END), 0) as total_out,
            COALESCE(SUM(sl.quantity), 0) as total_movement
        FROM products p
        LEFT JOIN stock_logs sl ON p.id = sl.product_id 
            AND MONTH(sl.created_at) = $month AND YEAR(sl.created_at) = $year
        GROUP BY p.id
        HAVING total_movement > 0
        ORDER BY total_movement DESC
        LIMIT $top_per_page OFFSET $top_offset
    ");
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Reports - Inventory System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .transition-margin {
            transition: margin-left 0.3s ease-in-out;
        }
    </style>
</head>

<body class="bg-gray-50 flex">
    <!-- ================= SIDEBAR ================= -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- ================= MAIN CONTENT ================= -->
    <div id="mainContent" class="flex-1 lg:ml-72 p-4 md:p-6 lg:p-8 transition-margin">
        <!-- Header -->
        <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Reports</h1>
                <p class="text-gray-500 text-sm mt-1">Simple overview of your inventory</p>
            </div>

            <!-- Month Filter -->
            <form method="GET" class="mt-4 md:mt-0 flex space-x-2">
                <select name="month" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <?php for ($i = 1; $i <= 12; $i++):
                        $name = date('F', mktime(0, 0, 0, $i, 1));
                    ?>
                        <option value="<?= $i ?>" <?= $month == $i ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <?php for ($i = date('Y'); $i >= date('Y') - 2; $i--): ?>
                        <option value="<?= $i ?>" <?= $year == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                    Go
                </button>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-box text-blue-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-xs text-gray-500">Total Products</p>
                        <p class="text-xl font-semibold text-gray-800"><?= number_format((float)($inv_data['total_products'] ?? 0)) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cedi-sign text-green-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-xs text-gray-500">Inventory Value</p>
                        <p class="text-xl font-semibold text-gray-800">₵<?= number_format((float)($inv_data['total_value'] ?? 0), 2) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line text-purple-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-xs text-gray-500">Monthly Movement</p>
                        <p class="text-xl font-semibold text-gray-800"><?= number_format((float)($monthly_data['total_transactions'] ?? 0)) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                <div class="flex items-center">
                    <div class="w-10 h-10 <?= ($inv_data['low_stock'] ?? 0) > 0 ? 'bg-red-100' : 'bg-yellow-100' ?> rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle <?= ($inv_data['low_stock'] ?? 0) > 0 ? 'text-red-600' : 'text-yellow-600' ?>"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-xs text-gray-500">Low Stock</p>
                        <p class="text-xl font-semibold <?= ($inv_data['low_stock'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-800' ?>"><?= (int)($inv_data['low_stock'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Summary -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-3"><?= $month_name ?> <?= $year ?> Summary</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-3 bg-green-50 rounded-lg">
                    <p class="text-xs text-green-600">Stock In</p>
                    <p class="text-xl font-semibold text-green-700">+<?= number_format((float)($monthly_data['total_in'] ?? 0)) ?></p>
                </div>
                <div class="p-3 bg-red-50 rounded-lg">
                    <p class="text-xs text-red-600">Stock Out</p>
                    <p class="text-xl font-semibold text-red-700">-<?= number_format((float)($monthly_data['total_out'] ?? 0)) ?></p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg">
                    <p class="text-xs text-blue-600">Net Movement</p>
                    <p class="text-xl font-semibold text-blue-700"><?= number_format((float)(($monthly_data['total_in'] ?? 0) - ($monthly_data['total_out'] ?? 0))) ?></p>
                </div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Top Products -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Top Moving Products</h2>
                <?php if (mysqli_num_rows($top_products) > 0): ?>
                    <div class="space-y-3">
                        <?php while ($p = mysqli_fetch_assoc($top_products)): ?>
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                                <div>
                                    <p class="font-medium text-gray-800"><?= htmlspecialchars($p['name']) ?></p>
                                    <p class="text-xs text-gray-500">SKU: <?= htmlspecialchars($p['sku']) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-800"><?= (int)$p['total_movement'] ?> units</p>
                                    <p class="text-xs">
                                        <span class="text-green-600">IN: <?= (int)$p['total_in'] ?></span>
                                        <span class="text-red-600 ml-1">OUT: <?= (int)$p['total_out'] ?></span>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <?php renderPagination($top_page, $top_total_pages, 'top_page'); ?>
                <?php else: ?>
                    <p class="text-gray-500 text-sm text-center py-4">No movement this month</p>
                <?php endif; ?>
            </div>

            <!-- Low Stock Alerts -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Low Stock Alert</h2>
                <?php if (mysqli_num_rows($low_stock) > 0): ?>
                    <div class="space-y-3">
                        <?php while ($item = mysqli_fetch_assoc($low_stock)): ?>
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                                <div>
                                    <p class="font-medium text-gray-800"><?= htmlspecialchars($item['name']) ?></p>
                                    <p class="text-xs text-gray-500">SKU: <?= htmlspecialchars($item['sku']) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-red-600"><?= (int)$item['quantity'] ?> units</p>
                                    <p class="text-xs text-gray-500">₵<?= number_format((float)$item['price'], 2) ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <?php renderPagination($low_stock_page, $low_stock_total_pages, 'low_stock_page'); ?>
                <?php else: ?>
                    <p class="text-gray-500 text-sm text-center py-4">No low stock items</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Movements -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800">Recent Stock Movements</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">Date</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">Product</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">SKU</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">Type</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">Quantity</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (mysqli_num_rows($movements) > 0): ?>
                            <?php while ($m = mysqli_fetch_assoc($movements)): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-gray-600"><?= date('M d, H:i', strtotime($m['date'])) ?></td>
                                    <td class="px-4 py-2 font-medium text-gray-800"><?= htmlspecialchars($m['product_name']) ?></td>
                                    <td class="px-4 py-2 text-gray-500 font-mono"><?= htmlspecialchars($m['sku']) ?></td>
                                    <td class="px-4 py-2">
                                        <?php if ($m['change_type'] == 'IN'): ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-600 rounded-full text-xs">IN</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-red-100 text-red-600 rounded-full text-xs">OUT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 <?= $m['change_type'] == 'IN' ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                                        <?= $m['change_type'] == 'IN' ? '+' : '-' ?><?= (int)$m['quantity'] ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500"><?= htmlspecialchars($m['note'] ?? '-') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    <i class="fas fa-history text-3xl mb-2 text-gray-300"></i>
                                    <p>No movements this month</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-4 pb-4">
                <?php renderPagination($movements_page, $movements_total_pages, 'movements_page'); ?>
            </div>
        </div>

        <!-- Simple Export -->
        <div class="mt-5 text-right">
            <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700">
                <i class="fas fa-print mr-2"></i>
                Print Report
            </button>
        </div>
    </div>
</body>

</html>