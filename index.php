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

// ------------------------------
// HANDLE ADD PRODUCT
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "add") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $price = $_POST['price'];
    $quantity = $_POST['quantity'] ?? 0;
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] != '' ? (int)$_POST['category_id'] : 'NULL';

    mysqli_query($conn, "INSERT INTO products (name, sku, price, quantity, category_id) VALUES ('$name','$sku','$price', $quantity, $category_id)");

    // Log the activity
    $product_id = mysqli_insert_id($conn);
    mysqli_query($conn, "INSERT INTO stock_logs (product_id, change_type, quantity, note) 
                        VALUES ($product_id, 'IN', $quantity, 'Product created')");

    exit("success");
}

// ------------------------------
// HANDLE EDIT PRODUCT
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "edit") {
    $id = $_POST['id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $price = $_POST['price'];
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] != '' ? (int)$_POST['category_id'] : 'NULL';

    mysqli_query($conn, "UPDATE products SET name='$name', sku='$sku', price='$price', category_id=$category_id WHERE id=$id");
    exit("success");
}

// ------------------------------
// HANDLE DELETE PRODUCT
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "delete") {
    $id = $_POST['id'];

    // Delete related stock logs first
    mysqli_query($conn, "DELETE FROM stock_logs WHERE product_id=$id");
    // Then delete product
    mysqli_query($conn, "DELETE FROM products WHERE id=$id");

    exit("success");
}

// ------------------------------
// FETCH CATEGORIES FOR DROPDOWN
// ------------------------------
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");

// ------------------------------
// FETCH PRODUCTS FOR TABLE
// ------------------------------
$products = mysqli_query($conn, "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 10");

// ------------------------------
// FETCH DASHBOARD STATS - FIXED CALCULATIONS
// ------------------------------
$total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM products"))['count'];
$total_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(quantity), 0) as total FROM products"))['total'];
$total_value = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(price * quantity), 0) as total FROM products"))['total'];
$low_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE quantity < 10"))['count'];

// ------------------------------
// FETCH CATEGORY STATISTICS - FIXED: USING category_id IN products TABLE
// ------------------------------
$category_stats = mysqli_query($conn, "
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category_name,
        COUNT(DISTINCT p.id) as product_count,
        COALESCE(SUM(p.quantity), 0) as total_items,
        COALESCE(SUM(p.price * p.quantity), 0) as total_value
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    GROUP BY COALESCE(c.name, 'Uncategorized')
    ORDER BY total_value DESC
");

// ------------------------------
// FETCH SALES STATISTICS - FIXED: CORRECT AGGREGATION
// ------------------------------
$sales_stats = mysqli_query($conn, "
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category_name,
        COUNT(DISTINCT sl.product_id) as products_sold,
        COALESCE(SUM(sl.quantity), 0) as total_items_sold,
        COALESCE(SUM(sl.quantity * p.price), 0) as total_sales_amount
    FROM stock_logs sl
    JOIN products p ON sl.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE sl.change_type = 'OUT'
    AND sl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY COALESCE(c.name, 'Uncategorized')
    ORDER BY total_sales_amount DESC
");

$total_sales_summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(sl.quantity), 0) as total_items_sold,
        COALESCE(SUM(sl.quantity * p.price), 0) as total_revenue
    FROM stock_logs sl
    JOIN products p ON sl.product_id = p.id
    WHERE sl.change_type = 'OUT'
    AND sl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
"));

// Ensure we have values even if query returns null
$total_items_sold = $total_sales_summary['total_items_sold'] ?? 0;
$total_revenue = $total_sales_summary['total_revenue'] ?? 0;

// ------------------------------
// FETCH RECENT ACTIVITIES (LAST 7 DAYS) - WITH REAL-TIME TIMESTAMP
// ------------------------------
$recent_activities = mysqli_query($conn, "
    SELECT 
        sl.*,
        p.name as product_name,
        p.sku as product_sku,
        sl.created_at as activity_timestamp,
        UNIX_TIMESTAMP(sl.created_at) as activity_unix_timestamp
    FROM stock_logs sl
    JOIN products p ON sl.product_id = p.id
    WHERE sl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY sl.created_at DESC
");

// ------------------------------
// FETCH STOCK MOVEMENT SUMMARY
// ------------------------------
$stock_summary = mysqli_query($conn, "
    SELECT 
        DATE(created_at) as date,
        COALESCE(SUM(CASE WHEN change_type = 'IN' THEN quantity ELSE 0 END), 0) as total_in,
        COALESCE(SUM(CASE WHEN change_type = 'OUT' THEN quantity ELSE 0 END), 0) as total_out
    FROM stock_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");

// ------------------------------
// FETCH TOP PRODUCTS BY MOVEMENT
// ------------------------------
$top_products = mysqli_query($conn, "
    SELECT 
        p.id,
        p.name,
        p.sku,
        COALESCE(SUM(CASE WHEN sl.change_type = 'IN' THEN sl.quantity ELSE 0 END), 0) as total_in,
        COALESCE(SUM(CASE WHEN sl.change_type = 'OUT' THEN sl.quantity ELSE 0 END), 0) as total_out,
        COALESCE(SUM(sl.quantity), 0) as total_movement
    FROM products p
    LEFT JOIN stock_logs sl ON p.id = sl.product_id
        AND sl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY p.id, p.name, p.sku
    HAVING total_movement > 0
    ORDER BY total_movement DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html>

<head>
    <title>Inventory Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .transition-margin {
            transition: margin-left 0.3s ease-in-out;
        }

        .stat-card {
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .activity-timeline {
            position: relative;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }

        /* Real-time timestamp styling */
        .time-ago {
            font-family: monospace;
            font-size: 0.7rem;
        }

        .timestamp-updated {
            animation: pulse 0.3s ease-in-out;
        }

        @keyframes pulse {
            0% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-gray-50 flex">
    <!-- ================= SIDEBAR ================= -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- ================= MAIN CONTENT ================= -->
    <div id="mainContent" class="flex-1 lg:ml-72 p-4 md:p-6 lg:p-8 transition-margin">
        <!-- Welcome Section with Real-time Clock -->
        <div class="mb-8 flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Welcome back, Adelaide</h1>
                <p class="text-gray-500 mt-1">Here's what's happening with your inventory today.</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm px-4 py-2 border border-gray-100">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-clock text-blue-500"></i>
                    <span id="realTimeClock" class="text-gray-700 font-mono text-sm"></span>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Products -->
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Products</p>
                        <p class="text-3xl font-bold text-gray-800"><?= (int)$total_products ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-box text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm text-gray-500">
                    <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                    <span>Active inventory items</span>
                </div>
            </div>

            <!-- Total Stock -->
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Stock</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format((float)$total_stock) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cubes text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm text-gray-500">
                    <i class="fas fa-layer-group text-gray-400 mr-1"></i>
                    <span>Units in stock</span>
                </div>
            </div>

            <!-- Total Value -->
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Inventory Value</p>
                        <p class="text-3xl font-bold text-gray-800">₵<?= number_format((float)$total_value, 2) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cedi-sign text-purple-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm text-gray-500">
                    <i class="fas fa-chart-line text-gray-400 mr-1"></i>
                    <span>Total asset value</span>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Low Stock Alert</p>
                        <p class="text-3xl font-bold <?= $low_stock > 0 ? 'text-red-600' : 'text-gray-800' ?>"><?= (int)$low_stock ?></p>
                    </div>
                    <div class="w-12 h-12 <?= $low_stock > 0 ? 'bg-red-50' : 'bg-yellow-50' ?> rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle <?= $low_stock > 0 ? 'text-red-600' : 'text-yellow-600' ?> text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <?php if ($low_stock > 0): ?>
                        <i class="fas fa-exclamation-circle text-red-500 mr-1"></i>
                        <span class="text-red-600"><?= (int)$low_stock ?> products need attention</span>
                    <?php else: ?>
                        <i class="fas fa-check-circle text-green-500 mr-1"></i>
                        <span class="text-green-600">All stock levels are healthy</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- NEW CARDS: Inventory by Category and Sales by Category -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Card 1: Total Items in Stock by Category -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-boxes mr-2 text-blue-600"></i>
                        Inventory Overview
                    </h2>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                        Current Stock
                    </span>
                </div>

                <div class="space-y-4">
                    <?php
                    $grand_total_items = 0;
                    $grand_total_value = 0;
                    mysqli_data_seek($category_stats, 0);
                    $cat_data = [];
                    while ($cat = mysqli_fetch_assoc($category_stats)) {
                        $cat_data[] = $cat;
                        $grand_total_items += (float)$cat['total_items'];
                        $grand_total_value += (float)$cat['total_value'];
                    }

                    if (!empty($cat_data)):
                        foreach ($cat_data as $cat):
                    ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($cat['category_name']) ?></span>
                                        <span class="ml-2 text-xs text-gray-500">(<?= (int)$cat['product_count'] ?> products)</span>
                                    </div>
                                    <div class="flex items-center mt-1">
                                        <?php $percentage = $grand_total_items > 0 ? ($cat['total_items'] / $grand_total_items) * 100 : 0; ?>
                                        <div class="w-24 h-2 bg-gray-200 rounded-full mr-2">
                                            <div class="h-2 bg-blue-600 rounded-full" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-600"><?= number_format((float)$cat['total_items']) ?> units</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-semibold text-gray-800">₵<?= number_format((float)$cat['total_value'], 2) ?></span>
                                    <span class="text-xs text-gray-500 block">value</span>
                                </div>
                            </div>
                        <?php
                        endforeach;
                    else:
                        ?>
                        <div class="text-center py-4 text-gray-500">
                            <p class="text-sm">No category data available</p>
                        </div>
                    <?php endif; ?>

                    <!-- Grand Total -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-semibold text-gray-700">Total Inventory:</span>
                            <div class="text-right">
                                <span class="text-sm font-bold text-gray-900"><?= number_format((float)$grand_total_items) ?> units</span>
                                <span class="text-sm font-bold text-blue-600 block">₵<?= number_format((float)$grand_total_value, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Total Items Sold by Category -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-shopping-cart mr-2 text-green-600"></i>
                        Sales Overview (Last 30 Days)
                    </h2>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                        <?= number_format((float)$total_items_sold) ?> items sold
                    </span>
                </div>

                <?php if ($total_items_sold > 0): ?>
                    <div class="space-y-4">
                        <?php
                        mysqli_data_seek($sales_stats, 0);
                        $grand_sales_items = 0;
                        $grand_sales_revenue = 0;
                        $sale_categories = [];
                        while ($sale = mysqli_fetch_assoc($sales_stats)) {
                            $sale_categories[] = $sale;
                            $grand_sales_items += (float)($sale['total_items_sold'] ?? 0);
                            $grand_sales_revenue += (float)($sale['total_sales_amount'] ?? 0);
                        }

                        foreach ($sale_categories as $sale):
                        ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($sale['category_name']) ?></span>
                                        <span class="ml-2 text-xs text-gray-500">(<?= (int)($sale['products_sold'] ?? 0) ?> products)</span>
                                    </div>
                                    <div class="flex items-center mt-1">
                                        <?php $percentage = $grand_sales_items > 0 ? (($sale['total_items_sold'] ?? 0) / $grand_sales_items) * 100 : 0; ?>
                                        <div class="w-24 h-2 bg-gray-200 rounded-full mr-2">
                                            <div class="h-2 bg-green-600 rounded-full" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-600"><?= number_format((float)($sale['total_items_sold'] ?? 0)) ?> units</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-semibold text-gray-800">₵<?= number_format((float)($sale['total_sales_amount'] ?? 0), 2) ?></span>
                                    <span class="text-xs text-gray-500 block">revenue</span>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Total Revenue -->
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-semibold text-gray-700">Total Revenue:</span>
                                <div class="text-right">
                                    <span class="text-sm font-bold text-gray-900"><?= number_format((float)$grand_sales_items) ?> units</span>
                                    <span class="text-sm font-bold text-green-600 block">₵<?= number_format((float)$grand_sales_revenue, 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-chart-line text-4xl mb-3 text-gray-300"></i>
                        <p class="text-sm">No sales data available for the last 30 days</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Charts and Activity Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Stock Movement Chart (Last 7 Days) -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-800">Stock Movement (Last 7 Days)</h2>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">Weekly Summary</span>
                </div>
                <div class="space-y-4">
                    <?php
                    $days = [];

                    // Fill last 7 days
                    for ($i = 6; $i >= 0; $i--) {
                        $date = date('Y-m-d', strtotime("-$i days"));
                        $days[date('M d', strtotime($date))] = ['in' => 0, 'out' => 0];
                    }

                    // Populate with actual data
                    if (mysqli_num_rows($stock_summary) > 0) {
                        mysqli_data_seek($stock_summary, 0);
                        while ($row = mysqli_fetch_assoc($stock_summary)) {
                            $day = date('M d', strtotime($row['date']));
                            if (isset($days[$day])) {
                                $days[$day]['in'] = (int)$row['total_in'];
                                $days[$day]['out'] = (int)$row['total_out'];
                            }
                        }
                    }

                    $max_movement = 1;
                    foreach ($days as $data) {
                        $max_movement = max($max_movement, $data['in'] + $data['out']);
                    }
                    ?>

                    <!-- Chart Bars -->
                    <?php foreach ($days as $day => $data): ?>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-gray-600"><?= $day ?></span>
                                <span class="text-gray-500">IN: <?= $data['in'] ?> | OUT: <?= $data['out'] ?></span>
                            </div>
                            <div class="flex space-x-1 h-8">
                                <div class="flex-1 bg-gray-100 rounded-l-lg relative">
                                    <?php if ($data['in'] > 0): ?>
                                        <div class="absolute inset-y-0 left-0 bg-green-500 rounded-l-lg"
                                            style="width: <?= ($data['in'] / $max_movement) * 100 ?>%"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 bg-gray-100 rounded-r-lg relative">
                                    <?php if ($data['out'] > 0): ?>
                                        <div class="absolute inset-y-0 right-0 bg-red-500 rounded-r-lg"
                                            style="width: <?= ($data['out'] / $max_movement) * 100 ?>%"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="flex items-center justify-end space-x-4 mt-4 text-sm">
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                            <span class="text-gray-600">Stock In</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                            <span class="text-gray-600">Stock Out</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities Timeline with Real-time Timestamps -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Activities</h2>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">Last 7 days</span>
                        <button onclick="refreshTimestamps()" class="text-gray-400 hover:text-gray-600 transition-colors" title="Refresh timestamps">
                            <i class="fas fa-sync-alt text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="activity-timeline space-y-4 max-h-96 overflow-y-auto pr-4" id="activityTimeline">
                    <?php if (mysqli_num_rows($recent_activities) > 0): ?>
                        <?php
                        $activities_data = [];
                        while ($activity = mysqli_fetch_assoc($recent_activities)) {
                            $activities_data[] = $activity;
                        }
                        ?>
                        <?php foreach ($activities_data as $activity):
                            $timestamp = (int)($activity['activity_unix_timestamp'] ?? 0);
                            if ($timestamp <= 0) {
                                $timestamp = strtotime($activity['activity_timestamp']);
                            }
                        ?>
                            <div class="flex items-start ml-8 relative activity-item" data-timestamp="<?= $timestamp ?>" data-original-time="<?= htmlspecialchars($activity['activity_timestamp']) ?>">
                                <div class="absolute -left-8 mt-1.5">
                                    <?php if ($activity['change_type'] == 'IN'): ?>
                                        <div class="w-4 h-4 bg-green-500 rounded-full border-2 border-white shadow"></div>
                                    <?php else: ?>
                                        <div class="w-4 h-4 bg-red-500 rounded-full border-2 border-white shadow"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800">
                                        <?= htmlspecialchars($activity['product_name']) ?>
                                        <span class="text-gray-500 font-normal">(<?= htmlspecialchars($activity['product_sku']) ?>)</span>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php if ($activity['change_type'] == 'IN'): ?>
                                            <span class="text-green-600 font-medium">+<?= (int)$activity['quantity'] ?></span> units added
                                        <?php else: ?>
                                            <span class="text-red-600 font-medium">-<?= (int)$activity['quantity'] ?></span> units removed
                                        <?php endif; ?>
                                        <?php if (!empty($activity['note'])): ?>
                                            · <?= htmlspecialchars($activity['note']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1 time-ago" id="timeAgo-<?= $timestamp ?>">
                                        <?= getTimeAgo($timestamp) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-history text-4xl mb-3 text-gray-300"></i>
                            <p class="text-sm">No activities in the last 7 days</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Products Section (Full Width) -->
        <div class="grid grid-cols-1 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-800">Top Moving Products</h2>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">Last 7 days</span>
                </div>

                <div class="space-y-4">
                    <?php if (mysqli_num_rows($top_products) > 0): ?>
                        <?php while ($product = mysqli_fetch_assoc($top_products)): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($product['name']) ?></span>
                                        <span class="text-xs text-gray-500 ml-2">(<?= htmlspecialchars($product['sku']) ?>)</span>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-500 mt-1">
                                        <span class="text-green-600 mr-3">IN: <?= (int)$product['total_in'] ?></span>
                                        <span class="text-red-600">OUT: <?= (int)$product['total_out'] ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-semibold text-gray-800"><?= (int)$product['total_movement'] ?></span>
                                    <span class="text-xs text-gray-500 block">total moves</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-chart-bar text-4xl mb-3 text-gray-300"></i>
                            <p class="text-sm">No movement data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Products Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Recently Added Products</h2>
                <button onclick="openModal('addModal')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                    <i class="fas fa-plus mr-2"></i>Add Product
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (mysqli_num_rows($products) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($products)) { ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-box text-blue-600 text-sm"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($row['name']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500"><?= htmlspecialchars($row['sku']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm text-gray-600 mr-2"><?= (int)$row['quantity'] ?></span>
                                            <div class="w-16 h-2 bg-gray-200 rounded-full">
                                                <div class="h-2 bg-blue-600 rounded-full" style="width: <?= min(100, ((int)$row['quantity'] / 50) * 100) ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">₵<?= number_format((float)$row['price'], 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($row['quantity'] < 10): ?>
                                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-600">Low Stock</span>
                                        <?php elseif ($row['quantity'] < 20): ?>
                                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-600">Medium</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-600">Good</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button onclick='openEditModal(<?= $row['id'] ?>, "<?= addslashes($row['name']) ?>", "<?= addslashes($row['sku']) ?>", <?= $row['price'] ?>, <?= $row['category_id'] ?? 'null' ?>)' class="text-blue-600 hover:text-blue-800 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick='openDeleteModal(<?= $row['id'] ?>)' class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-box-open text-4xl mb-3 text-gray-300"></i>
                                    <p class="text-sm">No products found. Click "Add Product" to create one.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= TOAST ================= -->
    <div id="toast" class="fixed bottom-4 right-4 z-50 hidden">
        <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span id="toastMessage">Success!</span>
        </div>
    </div>

    <!-- ================= ADD MODAL ================= -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-96 rounded-lg shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Add Product</h2>
                <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="addForm" onsubmit="event.preventDefault(); submitAdd();">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Name</label>
                    <input type="text" name="name" placeholder="Enter product name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">SKU</label>
                    <input type="text" name="sku" placeholder="Enter SKU"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Category --</option>
                        <?php
                        mysqli_data_seek($categories, 0);
                        while ($cat = mysqli_fetch_assoc($categories)):
                        ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Price (₵)</label>
                    <input type="number" name="price" placeholder="Enter price in Cedis" step="0.01"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Initial Quantity</label>
                    <input type="number" name="quantity" value="0" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                        Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= EDIT MODAL ================= -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-96 rounded-lg shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Edit Product</h2>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="editForm" onsubmit="event.preventDefault(); submitEdit();">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Name</label>
                    <input type="text" name="name" id="edit_name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">SKU</label>
                    <input type="text" name="sku" id="edit_sku"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category_id" id="edit_category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Category --</option>
                        <?php
                        mysqli_data_seek($categories, 0);
                        while ($cat = mysqli_fetch_assoc($categories)):
                        ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Price (₵)</label>
                    <input type="number" name="price" id="edit_price" step="0.01"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                        Update Product
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= DELETE MODAL ================= -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-80 rounded-lg shadow-xl text-center">
            <input type="hidden" id="delete_id">
            <div class="mb-4">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Delete Product?</h2>
                <p class="text-sm text-gray-500">This action cannot be undone.</p>
            </div>
            <div class="flex justify-center space-x-3">
                <button onclick="closeModal('deleteModal')"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                    Cancel
                </button>
                <button onclick="submitDelete()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Functions with Real-time Updates -->
    <script>
        // Helper function to get time ago string
        function getTimeAgo(timestamp) {
            const now = Math.floor(Date.now() / 1000);
            const diff = now - timestamp;

            if (diff < 60) {
                return 'just now';
            } else if (diff < 3600) {
                const minutes = Math.floor(diff / 60);
                return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
            } else if (diff < 86400) {
                const hours = Math.floor(diff / 3600);
                return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            } else if (diff < 604800) {
                const days = Math.floor(diff / 86400);
                return days + ' day' + (days > 1 ? 's' : '') + ' ago';
            } else {
                const date = new Date(timestamp * 1000);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        }

        // Update all timestamps in real-time
        function updateAllTimestamps() {
            const activityItems = document.querySelectorAll('.activity-item');
            activityItems.forEach(item => {
                const timestamp = parseInt(item.getAttribute('data-timestamp'));
                if (!isNaN(timestamp)) {
                    const timeSpan = item.querySelector('.time-ago');
                    if (timeSpan) {
                        const newTimeAgo = getTimeAgo(timestamp);
                        if (timeSpan.textContent !== newTimeAgo) {
                            timeSpan.textContent = newTimeAgo;
                            timeSpan.classList.add('timestamp-updated');
                            setTimeout(() => {
                                timeSpan.classList.remove('timestamp-updated');
                            }, 300);
                        }
                    }
                }
            });
        }

        // Refresh timestamps manually
        function refreshTimestamps() {
            updateAllTimestamps();
            showToast('Timestamps refreshed', 'success');
        }

        // Real-time clock update
        function updateRealTimeClock() {
            const now = new Date();
            const options = {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const clockElement = document.getElementById('realTimeClock');
            if (clockElement) {
                clockElement.textContent = now.toLocaleString('en-US', options);
            }
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Edit modal functions
        function openEditModal(id, name, sku, price, categoryId) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_sku').value = sku;
            document.getElementById('edit_price').value = price;
            const categorySelect = document.getElementById('edit_category_id');
            if (categoryId && categorySelect) {
                categorySelect.value = categoryId;
            }
            openModal('editModal');
        }

        // Delete modal functions
        function openDeleteModal(id) {
            document.getElementById('delete_id').value = id;
            openModal('deleteModal');
        }

        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const toastDiv = toast.querySelector('div');

            toastMessage.textContent = message;

            if (type === 'error') {
                toastDiv.classList.remove('bg-green-500');
                toastDiv.classList.add('bg-red-500');
            } else {
                toastDiv.classList.remove('bg-red-500');
                toastDiv.classList.add('bg-green-500');
            }

            toast.classList.remove('hidden');

            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }

        // Submit functions
        function submitAdd() {
            const form = document.getElementById('addForm');
            const formData = new FormData(form);
            formData.append('action', 'add');

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        showToast('Product added successfully!');
                        closeModal('addModal');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast('Error adding product', 'error');
                    }
                })
                .catch(error => {
                    showToast('Error adding product', 'error');
                });
        }

        function submitEdit() {
            const form = document.getElementById('editForm');
            const formData = new FormData(form);
            formData.append('action', 'edit');

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        showToast('Product updated successfully!');
                        closeModal('editModal');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast('Error updating product', 'error');
                    }
                })
                .catch(error => {
                    showToast('Error updating product', 'error');
                });
        }

        function submitDelete() {
            const id = document.getElementById('delete_id').value;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        showToast('Product deleted successfully!');
                        closeModal('deleteModal');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast('Error deleting product', 'error');
                    }
                })
                .catch(error => {
                    showToast('Error deleting product', 'error');
                });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['addModal', 'editModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }

        // Initialize real-time features
        document.addEventListener('DOMContentLoaded', function() {
            // Update clock immediately
            updateRealTimeClock();
            // Update timestamps immediately
            updateAllTimestamps();

            // Set intervals for real-time updates
            setInterval(updateRealTimeClock, 1000); // Update clock every second
            setInterval(updateAllTimestamps, 30000); // Update timestamps every 30 seconds
        });
    </script>
</body>

</html>
<?php
// Helper function for time ago calculation (PHP fallback)
function getTimeAgo($timestamp)
{
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, H:i', $timestamp);
    }
}
?>