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
// FUNCTION TO GENERATE SKU
// ------------------------------
function generateSKU($conn, $category_id = null) {
    // Get category prefix
    $prefix = 'PRD'; // Default prefix
    
    if ($category_id) {
        $cat_query = mysqli_query($conn, "SELECT name FROM categories WHERE id = $category_id");
        if ($cat_row = mysqli_fetch_assoc($cat_query)) {
            // Take first 3 letters of category name, uppercase
            $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $cat_row['name']), 0, 3));
            if (strlen($prefix) < 3) {
                $prefix = str_pad($prefix, 3, 'X');
            }
        }
    }
    
    // Generate unique number
    $unique = strtoupper(substr(uniqid(), -6));
    
    // Add random letters for more uniqueness
    $letters = '';
    for ($i = 0; $i < 2; $i++) {
        $letters .= chr(rand(65, 90));
    }
    
    $sku = $prefix . '-' . $unique . $letters;
    
    // Check if SKU already exists
    $check = mysqli_query($conn, "SELECT id FROM products WHERE sku = '$sku'");
    if (mysqli_num_rows($check) > 0) {
        // If exists, generate a new one recursively
        return generateSKU($conn, $category_id);
    }
    
    return $sku;
}

// ------------------------------
// HANDLE GENERATE SKU AJAX
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "generate_sku") {
    $category_id = $_POST['category_id'] ?? null;
    $sku = generateSKU($conn, $category_id);
    exit($sku);
}

// ------------------------------
// HANDLE ADD PRODUCT
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "add") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $price = $_POST['price'];
    $category_id = $_POST['category_id'] ?: 'NULL';
    $quantity = $_POST['quantity'] ?? 0;
    
    // Check if SKU already exists
    $check = mysqli_query($conn, "SELECT id FROM products WHERE sku = '$sku'");
    if (mysqli_num_rows($check) > 0) {
        exit("error: SKU already exists. Please generate a new one.");
    }
    
    $query = "INSERT INTO products (name, sku, price, category_id, quantity) 
              VALUES ('$name', '$sku', $price, $category_id, $quantity)";
    
    if(mysqli_query($conn, $query)) {
        $product_id = mysqli_insert_id($conn);
        
        // Log stock entry
        if($quantity > 0) {
            mysqli_query($conn, "INSERT INTO stock_logs (product_id, change_type, quantity, note) 
                                VALUES ($product_id, 'IN', $quantity, 'Initial stock')");
        }
        
        exit("success");
    } else {
        exit("error: " . mysqli_error($conn));
    }
}

// ------------------------------
// HANDLE EDIT PRODUCT
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "edit") {
    $id = $_POST['id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $price = $_POST['price'];
    $category_id = $_POST['category_id'] ?: 'NULL';
    
    // Check if SKU already exists for another product
    $check = mysqli_query($conn, "SELECT id FROM products WHERE sku = '$sku' AND id != $id");
    if (mysqli_num_rows($check) > 0) {
        exit("error: SKU already exists for another product.");
    }
    
    $query = "UPDATE products SET 
              name='$name', 
              sku='$sku', 
              price=$price, 
              category_id=$category_id 
              WHERE id=$id";
    
    if(mysqli_query($conn, $query)) {
        exit("success");
    } else {
        exit("error: " . mysqli_error($conn));
    }
}

// ------------------------------
// HANDLE DELETE PRODUCT
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "delete") {
    $id = $_POST['id'];
    
    // First delete related stock logs
    mysqli_query($conn, "DELETE FROM stock_logs WHERE product_id=$id");
    // Then delete product
    mysqli_query($conn, "DELETE FROM products WHERE id=$id");
    
    exit("success");
}

// ------------------------------
// HANDLE STOCK UPDATE
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "update_stock") {
    $id = $_POST['id'];
    $change_type = $_POST['change_type'];
    $quantity = $_POST['quantity'];
    $note = mysqli_real_escape_string($conn, $_POST['note'] ?? '');
    
    // Update product quantity
    if($change_type == 'IN') {
        mysqli_query($conn, "UPDATE products SET quantity = quantity + $quantity WHERE id=$id");
    } else {
        mysqli_query($conn, "UPDATE products SET quantity = quantity - $quantity WHERE id=$id");
    }
    
    // Log the movement
    mysqli_query($conn, "INSERT INTO stock_logs (product_id, change_type, quantity, note) 
                        VALUES ($id, '$change_type', $quantity, '$note')");
    
    exit("success");
}

// ------------------------------
// HANDLE FETCH PRODUCT DETAILS FOR VIEW MODAL
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "get_product_details") {
    $id = $_POST['id'];
    
    $query = mysqli_query($conn, "
        SELECT p.*, c.name as category_name,
        (SELECT COUNT(*) FROM stock_logs WHERE product_id = p.id) as total_movements,
        (SELECT SUM(CASE WHEN change_type = 'IN' THEN quantity ELSE 0 END) FROM stock_logs WHERE product_id = p.id) as total_in,
        (SELECT SUM(CASE WHEN change_type = 'OUT' THEN quantity ELSE 0 END) FROM stock_logs WHERE product_id = p.id) as total_out
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = $id
    ");
    
    if ($row = mysqli_fetch_assoc($query)) {
        // Get recent stock movements
        $logs_query = mysqli_query($conn, "
            SELECT * FROM stock_logs 
            WHERE product_id = $id 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        
        $logs = [];
        while ($log = mysqli_fetch_assoc($logs_query)) {
            $logs[] = $log;
        }
        
        $row['recent_movements'] = $logs;
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Product not found']);
    }
    exit;
}

// ------------------------------
// HANDLE EXPORT PRODUCTS
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "export") {
    $format = $_POST['format'] ?? 'csv';
    $filter = $_POST['filter'] ?? 'all';
    $category_filter = $_POST['category_filter'] ?? 'all';
    
    // Build query based on filters
    $where = [];
    if ($filter == 'low_stock') {
        $where[] = "p.quantity < 10";
    } elseif ($filter == 'out_of_stock') {
        $where[] = "p.quantity = 0";
    } elseif ($filter == 'in_stock') {
        $where[] = "p.quantity > 0";
    }
    
    if ($category_filter != 'all') {
        $where[] = "p.category_id = " . intval($category_filter);
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $query = mysqli_query($conn, "
        SELECT p.name, p.sku, p.price, p.quantity, 
        COALESCE(c.name, 'Uncategorized') as category_name,
        (SELECT SUM(CASE WHEN change_type = 'IN' THEN quantity ELSE 0 END) FROM stock_logs WHERE product_id = p.id) as total_in,
        (SELECT SUM(CASE WHEN change_type = 'OUT' THEN quantity ELSE 0 END) FROM stock_logs WHERE product_id = p.id) as total_out
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        $where_clause
        ORDER BY p.name
    ");
    
    $products = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $products[] = $row;
    }
    
    if ($format == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'SKU', 'Category', 'Price', 'Current Stock', 'Total In', 'Total Out']);
        
        foreach ($products as $product) {
            fputcsv($output, [
                $product['name'],
                $product['sku'],
                $product['category_name'],
                '$' . number_format($product['price'], 2),
                $product['quantity'],
                $product['total_in'] ?? 0,
                $product['total_out'] ?? 0
            ]);
        }
        fclose($output);
        exit;
    } elseif ($format == 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.json"');
        echo json_encode($products, JSON_PRETTY_PRINT);
        exit;
    } elseif ($format == 'pdf') {
        // For PDF, we'll generate HTML that can be printed as PDF
        $html = '<html><head><title>Products Export</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>';
        $html .= '</head><body>';
        $html .= '<h2>Products Export - ' . date('Y-m-d') . '</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Total In</th><th>Total Out</th></tr>';
        
        foreach ($products as $product) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($product['name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($product['sku']) . '</td>';
            $html .= '<td>' . htmlspecialchars($product['category_name']) . '</td>';
            $html .= '<td>$' . number_format($product['price'], 2) . '</td>';
            $html .= '<td>' . $product['quantity'] . '</td>';
            $html .= '<td>' . ($product['total_in'] ?? 0) . '</td>';
            $html .= '<td>' . ($product['total_out'] ?? 0) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        $html .= '</body></html>';
        
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.html"');
        echo $html;
        exit;
    }
}

// ------------------------------
// FETCH CATEGORIES FOR DROPDOWN
// ------------------------------
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");

// ------------------------------
// FETCH PRODUCTS WITH CATEGORIES
// ------------------------------
$products = mysqli_query($conn, "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Products - Inventory System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .transition-margin {
            transition: margin-left 0.3s ease-in-out;
        }
        .sku-input-group {
            display: flex;
            align-items: center;
        }
        .sku-input-group input {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .sku-input-group button {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        /* Quick actions panel */
        .quick-actions-panel {
            transition: all 0.3s ease;
            transform-origin: top;
        }
        
        .quick-actions-panel.hidden-panel {
            opacity: 0;
            transform: scaleY(0);
            max-height: 0;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        
        .quick-actions-panel.visible-panel {
            opacity: 1;
            transform: scaleY(1);
            max-height: 300px;
            margin-bottom: 1.5rem;
        }
        
        /* Toast animation */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast-show {
            animation: slideIn 0.3s ease-out;
        }
        
        /* Search highlight */
        .search-highlight {
            background-color: #fef3c7;
            transition: background-color 0.3s ease;
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body class="bg-gray-100 flex">
    <!-- ================= SIDEBAR ================= -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- ================= MAIN CONTENT ================= -->
    <div id="mainContent" class="flex-1 lg:ml-72 p-4 md:p-6 lg:p-8 transition-margin">
        <!-- Header with actions -->
        <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">Products</h1>
                <p class="text-gray-600">Manage your inventory products</p>
            </div>
            <div class="mt-4 md:mt-0 flex space-x-3">
                <!-- Quick Actions Button -->
                <div class="relative">
                    <button id="quickActionsBtn" onclick="toggleQuickActions()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors duration-200 flex items-center">
                        <i class="fas fa-bolt mr-2 text-yellow-500"></i>
                        Quick Actions
                        <i class="fas fa-chevron-down ml-2 text-xs" id="quickActionsIcon"></i>
                    </button>
                </div>
                
                <a href="categories.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-tags mr-2"></i>
                    Categories
                </a>
                <button onclick="openModal('addModal')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Add Product
                </button>
            </div>
        </div>

        <!-- Quick Actions Panel (Hidden by default) -->
        <div id="quickActionsPanel" class="quick-actions-panel hidden-panel bg-white rounded-lg border border-gray-200 shadow-sm mb-6">
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            <i class="fas fa-search mr-1"></i> Search Products
                        </label>
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search by name, SKU, category..." 
                                   class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                   onkeyup="filterProducts()">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                            <button onclick="clearSearch()" id="clearSearchBtn" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 hidden">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Stock Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            <i class="fas fa-filter mr-1"></i> Stock Filter
                        </label>
                        <select id="stockFilter" onchange="filterProducts()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="all">All Products</option>
                            <option value="in_stock">In Stock</option>
                            <option value="low_stock">Low Stock (&lt;10)</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                    
                    <!-- Category Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            <i class="fas fa-tags mr-1"></i> Category Filter
                        </label>
                        <select id="categoryFilter" onchange="filterProducts()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="all">All Categories</option>
                            <?php 
                            mysqli_data_seek($categories, 0);
                            while($cat = mysqli_fetch_assoc($categories)) { ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    
                    <!-- Export -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            <i class="fas fa-download mr-1"></i> Export
                        </label>
                        <div class="flex space-x-2">
                            <select id="exportFilter" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="all">All Products</option>
                                <option value="in_stock">In Stock</option>
                                <option value="low_stock">Low Stock</option>
                                <option value="out_of_stock">Out of Stock</option>
                            </select>
                            <button onclick="exportProducts('csv')" class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors duration-200 text-sm" title="Export as CSV">
                                <i class="fas fa-file-csv"></i>
                            </button>
                            <button onclick="exportProducts('json')" class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200 text-sm" title="Export as JSON">
                                <i class="fas fa-file-code"></i>
                            </button>
                            <button onclick="exportProducts('pdf')" class="px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors duration-200 text-sm" title="Export as HTML/PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Search Results Info -->
                <div id="searchResultsInfo" class="mt-3 text-xs text-gray-500 hidden">
                    <span id="visibleCount"></span> products visible out of <span id="totalCount"></span>
                    <button onclick="clearSearch()" class="ml-2 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-times mr-1"></i>Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Product List</h2>
                <span class="text-sm text-gray-500" id="totalProductsCount">Total: <?= mysqli_num_rows($products) ?> products</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if(mysqli_num_rows($products) > 0): ?>
                            <?php 
                            $totalProducts = 0;
                            while($row = mysqli_fetch_assoc($products)) { 
                                $totalProducts++;
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200 product-row" 
                                data-name="<?= strtolower(htmlspecialchars($row['name'])) ?>"
                                data-sku="<?= strtolower(htmlspecialchars($row['sku'])) ?>"
                                data-category="<?= strtolower(htmlspecialchars($row['category_name'] ?? 'uncategorized')) ?>"
                                data-category-id="<?= $row['category_id'] ?? 0 ?>"
                                data-quantity="<?= $row['quantity'] ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 product-name"><?= htmlspecialchars($row['name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600 product-sku"><?= htmlspecialchars($row['sku']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap product-category">
                                    <?php if($row['category_name']): ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">
                                            <?= htmlspecialchars($row['category_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                            Uncategorized
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap product-quantity">
                                    <span class="<?= $row['quantity'] < 10 ? 'text-red-600 font-semibold' : 'text-gray-900' ?>">
                                        <?= $row['quantity'] ?>
                                    </span>
                                    <button onclick="openStockModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>', <?= $row['quantity'] ?>)" 
                                            class="ml-2 text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?= number_format($row['price'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="openViewModal(<?= $row['id'] ?>)" 
                                            class="text-green-600 hover:text-green-900 mr-3 transition-colors duration-200">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="openEditModal(
                                        <?= $row['id'] ?>,
                                        '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['sku'], ENT_QUOTES) ?>',
                                        <?= $row['price'] ?>,
                                        <?= $row['category_id'] ?: 'null' ?>
                                    )" class="text-blue-600 hover:text-blue-900 mr-3 transition-colors duration-200">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="openDeleteModal(<?= $row['id'] ?>)" class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-box-open text-4xl mb-3 text-gray-300"></i>
                                    <p>No products found. Click "Add Product" to create one.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Hidden total products count -->
    <input type="hidden" id="totalProductsCount" value="<?= $totalProducts ?? 0 ?>">

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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category_id" id="add_category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Category</option>
                        <?php 
                        mysqli_data_seek($categories, 0);
                        while($cat = mysqli_fetch_assoc($categories)) { ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">SKU (Auto-generated)</label>
                    <div class="flex">
                        <input type="text" name="sku" id="add_sku" readonly
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg bg-gray-50 font-mono text-sm" required>
                        <button type="button" onclick="generateSKU('add')" 
                                class="px-3 py-2 bg-blue-600 text-white rounded-r-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-sync-alt"></i> Generate
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">SKU is automatically generated based on category</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Price</label>
                    <input type="number" name="price" placeholder="Enter price" step="0.01" 
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category_id" id="edit_category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Category</option>
                        <?php 
                        mysqli_data_seek($categories, 0);
                        while($cat = mysqli_fetch_assoc($categories)) { ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">SKU</label>
                    <div class="flex">
                        <input type="text" name="sku" id="edit_sku" 
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg font-mono text-sm" required>
                        <button type="button" onclick="generateSKU('edit')" 
                                class="px-3 py-2 bg-blue-600 text-white rounded-r-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-sync-alt"></i> Regenerate
                        </button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Price</label>
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

    <!-- ================= VIEW MODAL ================= -->
    <div id="viewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-[500px] rounded-lg shadow-xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex justify-between items-center mb-4 sticky top-0 bg-white py-2">
                <h2 class="text-xl font-bold text-gray-800">Product Details</h2>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="viewProductDetails" class="space-y-4">
                <!-- Product details will be loaded here via JavaScript -->
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-blue-600"></i>
                    <p class="mt-2 text-gray-600">Loading product details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= STOCK MODAL ================= -->
    <div id="stockModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-96 rounded-lg shadow-xl">
            <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Update Stock - <span id="stock_product_name"></span></h2>
                <button onclick="closeModal('stockModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <p class="text-sm text-gray-600 mb-4">Current Stock: <span id="current_stock" class="font-semibold"></span></p>
            <form id="stockForm" onsubmit="event.preventDefault(); submitStock();">
                <input type="hidden" name="id" id="stock_id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Change Type</label>
                    <select name="change_type" id="change_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="IN">Add Stock (IN)</option>
                        <option value="OUT">Remove Stock (OUT)</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                    <input type="number" name="quantity" id="stock_quantity" min="1" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Note (Optional)</label>
                    <input type="text" name="note" id="stock_note" placeholder="e.g., New shipment, damaged, etc." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('stockModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                        Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= DELETE MODAL ================= -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-80 rounded-lg shadow-xl text-center">
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

    <!-- ================= TOAST CONTAINER ================= -->
    <div id="toastContainer" class="fixed top-5 right-5 z-50 space-y-2"></div>

    <script>
        // ----------------------------- QUICK ACTIONS PANEL -----------------------------
        let quickActionsVisible = false;
        
        function toggleQuickActions() {
            const panel = document.getElementById('quickActionsPanel');
            const icon = document.getElementById('quickActionsIcon');
            
            if (quickActionsVisible) {
                panel.classList.remove('visible-panel');
                panel.classList.add('hidden-panel');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                panel.classList.remove('hidden-panel');
                panel.classList.add('visible-panel');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
            
            quickActionsVisible = !quickActionsVisible;
        }

        // ----------------------------- SEARCH AND FILTER -----------------------------
        function filterProducts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const stockFilter = document.getElementById('stockFilter').value;
            const categoryFilter = document.getElementById('categoryFilter').value;
            const rows = document.querySelectorAll('.product-row');
            
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.dataset.name;
                const sku = row.dataset.sku;
                const category = row.dataset.category;
                const categoryId = row.dataset.categoryId;
                const quantity = parseInt(row.dataset.quantity);
                
                // Check search match
                const searchMatch = searchTerm === '' || 
                                   name.includes(searchTerm) || 
                                   sku.includes(searchTerm) || 
                                   category.includes(searchTerm);
                
                // Check stock filter
                let stockMatch = true;
                switch(stockFilter) {
                    case 'in_stock':
                        stockMatch = quantity > 0;
                        break;
                    case 'low_stock':
                        stockMatch = quantity > 0 && quantity < 10;
                        break;
                    case 'out_of_stock':
                        stockMatch = quantity === 0;
                        break;
                }
                
                // Check category filter
                const categoryMatch = categoryFilter === 'all' || categoryId === categoryFilter;
                
                // Show/hide based on all conditions
                if (searchMatch && stockMatch && categoryMatch) {
                    row.style.display = '';
                    visibleCount++;
                    
                    // Highlight search term if present
                    if (searchTerm !== '') {
                        highlightSearchTerm(row, searchTerm);
                    } else {
                        removeHighlight(row);
                    }
                } else {
                    row.style.display = 'none';
                    removeHighlight(row);
                }
            });
            
            // Update search results info
            updateSearchResultsInfo(visibleCount, rows.length);
            
            // Show/hide clear button
            const clearBtn = document.getElementById('clearSearchBtn');
            if (searchTerm !== '' || stockFilter !== 'all' || categoryFilter !== 'all') {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
        }
        
        function highlightSearchTerm(row, term) {
            const nameElement = row.querySelector('.product-name');
            const skuElement = row.querySelector('.product-sku');
            const categoryElement = row.querySelector('.product-category');
            
            // Remove previous highlights
            removeHighlight(row);
            
            // Add highlight class if term matches
            if (nameElement && nameElement.textContent.toLowerCase().includes(term)) {
                nameElement.classList.add('search-highlight');
            }
            
            if (skuElement && skuElement.textContent.toLowerCase().includes(term)) {
                skuElement.classList.add('search-highlight');
            }
            
            if (categoryElement && categoryElement.textContent.toLowerCase().includes(term)) {
                categoryElement.classList.add('search-highlight');
            }
        }
        
        function removeHighlight(row) {
            const highlighted = row.querySelectorAll('.search-highlight');
            highlighted.forEach(el => el.classList.remove('search-highlight'));
        }
        
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('stockFilter').value = 'all';
            document.getElementById('categoryFilter').value = 'all';
            filterProducts();
        }
        
        function updateSearchResultsInfo(visible, total) {
            const infoDiv = document.getElementById('searchResultsInfo');
            const visibleSpan = document.getElementById('visibleCount');
            const totalSpan = document.getElementById('totalCount');
            
            if (visible < total) {
                visibleSpan.textContent = visible;
                totalSpan.textContent = total;
                infoDiv.classList.remove('hidden');
            } else {
                infoDiv.classList.add('hidden');
            }
        }

        // ----------------------------- EXPORT FUNCTIONALITY -----------------------------
        function exportProducts(format) {
            const exportFilter = document.getElementById('exportFilter').value;
            const categoryFilter = document.getElementById('categoryFilter').value;
            
            let data = new FormData();
            data.append("action", "export");
            data.append("format", format);
            data.append("filter", exportFilter);
            data.append("category_filter", categoryFilter);
            
            // Show loading toast
            showToast(`Exporting products as ${format.toUpperCase()}...`, "info", 2000);
            
            fetch("products.php", { 
                method: "POST", 
                body: data,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Export failed');
                return response.blob();
            })
            .then(blob => {
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `products_export_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                showToast(`Products exported successfully!`, "success");
            })
            .catch(error => {
                showToast("Error exporting products", "error");
            });
        }

        // ----------------------------- MODALS -----------------------------
        function openModal(id){ document.getElementById(id).classList.remove("hidden"); }
        function closeModal(id){ document.getElementById(id).classList.add("hidden"); }

        // ----------------------------- TOAST SYSTEM -----------------------------
        function showToast(message, type = "success", duration = 3000) {
            const container = document.getElementById('toastContainer');
            
            const config = {
                success: {
                    bg: 'bg-green-600',
                    icon: 'fa-check-circle',
                    title: 'Success'
                },
                error: {
                    bg: 'bg-red-600',
                    icon: 'fa-exclamation-circle',
                    title: 'Error'
                },
                warning: {
                    bg: 'bg-yellow-600',
                    icon: 'fa-exclamation-triangle',
                    title: 'Warning'
                },
                info: {
                    bg: 'bg-blue-600',
                    icon: 'fa-info-circle',
                    title: 'Info'
                }
            };
            
            const toastConfig = config[type] || config.info;
            
            const toast = document.createElement('div');
            toast.className = `${toastConfig.bg} text-white px-4 py-3 rounded-lg shadow-lg flex items-center space-x-3 toast-show min-w-[300px]`;
            toast.innerHTML = `
                <i class="fas ${toastConfig.icon}"></i>
                <div class="flex-1">
                    <p class="font-medium text-sm">${toastConfig.title}</p>
                    <p class="text-xs opacity-90">${message}</p>
                </div>
                <button onclick="this.parentElement.remove()" class="hover:opacity-75">
                    <i class="fas fa-times text-sm"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'slideIn 0.3s ease-out reverse';
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.remove();
                        }
                    }, 300);
                }
            }, duration);
        }

        // ----------------------------- GENERATE SKU -----------------------------
        function generateSKU(formType) {
            let categoryId;
            
            if (formType === 'add') {
                categoryId = document.getElementById('add_category_id').value;
            } else {
                categoryId = document.getElementById('edit_category_id').value;
            }
            
            let data = new FormData();
            data.append("action", "generate_sku");
            data.append("category_id", categoryId);
            
            fetch("products.php", { method: "POST", body: data })
                .then(res => res.text())
                .then(sku => {
                    if (formType === 'add') {
                        document.getElementById('add_sku').value = sku;
                    } else {
                        document.getElementById('edit_sku').value = sku;
                    }
                    showToast("SKU generated successfully!");
                })
                .catch(error => {
                    showToast("Error generating SKU", "error");
                });
        }

        // Auto-generate SKU when category changes in add modal
        document.getElementById('add_category_id')?.addEventListener('change', function() {
            generateSKU('add');
        });

        // Auto-generate SKU when category changes in edit modal
        document.getElementById('edit_category_id')?.addEventListener('change', function() {
            generateSKU('edit');
        });

        // Generate initial SKU when add modal opens
        document.addEventListener('DOMContentLoaded', function() {
            const addModal = document.getElementById('addModal');
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        if (!addModal.classList.contains('hidden')) {
                            setTimeout(() => generateSKU('add'), 100);
                        }
                    }
                });
            });
            
            observer.observe(addModal, { attributes: true });
        });

        // ----------------------------- ADD PRODUCT -----------------------------
        function submitAdd(){
            let form = document.getElementById("addForm");
            let data = new FormData(form);
            data.append("action","add");
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            submitBtn.disabled = true;
            
            fetch("products.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('addModal'); 
                        showToast("Product Added Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.replace("error: ", ""), "error");
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    showToast("An error occurred", "error");
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        }

        // ----------------------------- EDIT PRODUCT -----------------------------
        function openEditModal(id, name, sku, price, category_id){
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_sku").value = sku;
            document.getElementById("edit_price").value = price;
            
            let categorySelect = document.getElementById("edit_category_id");
            if(category_id) {
                categorySelect.value = category_id;
            } else {
                categorySelect.value = "";
            }
            
            openModal('editModal');
        }
        
        function submitEdit(){
            let form = document.getElementById("editForm");
            let data = new FormData(form);
            data.append("action","edit");
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
            submitBtn.disabled = true;
            
            fetch("products.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('editModal'); 
                        showToast("Product Updated Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.replace("error: ", ""), "error");
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    showToast("An error occurred", "error");
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        }

        // ----------------------------- VIEW PRODUCT -----------------------------
        function openViewModal(id) {
            openModal('viewModal');
            
            document.getElementById('viewProductDetails').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-blue-600"></i>
                    <p class="mt-2 text-gray-600">Loading product details...</p>
                </div>
            `;
            
            let data = new FormData();
            data.append("action", "get_product_details");
            data.append("id", id);
            
            fetch("products.php", { method: "POST", body: data })
                .then(res => res.json())
                .then(product => {
                    if (product.error) {
                        document.getElementById('viewProductDetails').innerHTML = `
                            <div class="text-center py-8 text-red-600">
                                <i class="fas fa-exclamation-circle text-4xl mb-3"></i>
                                <p>${product.error}</p>
                            </div>
                        `;
                        return;
                    }
                    
                    const createdDate = product.created_at ? new Date(product.created_at).toLocaleString() : 'N/A';
                    
                    let movementsHtml = '';
                    if (product.recent_movements && product.recent_movements.length > 0) {
                        movementsHtml = product.recent_movements.map(movement => {
                            const movementDate = movement.created_at ? new Date(movement.created_at).toLocaleString() : 'N/A';
                            return `
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                    <div>
                                        <span class="px-2 py-1 text-xs rounded-full ${movement.change_type === 'IN' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                            ${movement.change_type}
                                        </span>
                                        <span class="ml-2 text-sm text-gray-600">${movement.quantity} units</span>
                                    </div>
                                    <div class="text-xs text-gray-500">${movementDate}</div>
                                </div>
                            `;
                        }).join('');
                    } else {
                        movementsHtml = '<p class="text-sm text-gray-500 italic">No stock movements recorded</p>';
                    }
                    
                    const html = `
                        <div class="space-y-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-gray-700 mb-3">Basic Information</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-500">Product Name</p>
                                        <p class="font-medium">${escapeHtml(product.name)}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">SKU</p>
                                        <p class="font-mono font-medium">${escapeHtml(product.sku)}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Category</p>
                                        <p>${product.category_name ? escapeHtml(product.category_name) : '<span class="text-gray-400">Uncategorized</span>'}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Price</p>
                                        <p class="font-medium">$${parseFloat(product.price).toFixed(2)}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-gray-700 mb-3">Stock Information</h3>
                                <div class="grid grid-cols-3 gap-4 mb-3">
                                    <div class="text-center">
                                        <p class="text-2xl font-bold ${product.quantity < 10 ? 'text-red-600' : 'text-gray-900'}">${product.quantity}</p>
                                        <p class="text-xs text-gray-500">Current Stock</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-green-600">${product.total_in || 0}</p>
                                        <p class="text-xs text-gray-500">Total In</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-red-600">${product.total_out || 0}</p>
                                        <p class="text-xs text-gray-500">Total Out</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-2">Recent Stock Movements</p>
                                    <div class="space-y-1 max-h-40 overflow-y-auto custom-scrollbar">
                                        ${movementsHtml}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-gray-700 mb-3">System Information</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-500">Total Movements</p>
                                        <p>${product.total_movements || 0}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Created</p>
                                        <p class="text-sm">${createdDate}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('viewProductDetails').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('viewProductDetails').innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i class="fas fa-exclamation-circle text-4xl mb-3"></i>
                            <p>Error loading product details</p>
                        </div>
                    `;
                });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ----------------------------- STOCK MANAGEMENT -----------------------------
        function openStockModal(id, name, currentStock){
            document.getElementById("stock_id").value = id;
            document.getElementById("stock_product_name").innerText = name;
            document.getElementById("current_stock").innerText = currentStock;
            document.getElementById("stock_quantity").value = "";
            document.getElementById("stock_note").value = "";
            document.getElementById("change_type").value = "IN";
            openModal('stockModal');
        }
        
        function submitStock(){
            let form = document.getElementById("stockForm");
            let data = new FormData(form);
            data.append("action","update_stock");
            
            let changeType = document.getElementById("change_type").value;
            let quantity = parseInt(document.getElementById("stock_quantity").value);
            let currentStock = parseInt(document.getElementById("current_stock").innerText);
            
            if(changeType === "OUT" && quantity > currentStock) {
                showToast("Not enough stock available!", "error");
                return;
            }
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
            submitBtn.disabled = true;
            
            fetch("products.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('stockModal'); 
                        showToast("Stock Updated Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast("Error: " + response, "error");
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    showToast("An error occurred", "error");
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        }

        // ----------------------------- DELETE PRODUCT -----------------------------
        let deleteID = null;
        function openDeleteModal(id){ deleteID = id; openModal('deleteModal'); }
        
        function submitDelete(){
            let data = new FormData();
            data.append("action","delete");
            data.append("id", deleteID);
            
            const deleteBtn = document.querySelector('#deleteModal button[onclick="submitDelete()"]');
            const originalText = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
            deleteBtn.disabled = true;
            
            fetch("products.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('deleteModal'); 
                        showToast("Product Deleted Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast("Error: " + response, "error");
                        deleteBtn.innerHTML = originalText;
                        deleteBtn.disabled = false;
                    }
                })
                .catch(error => {
                    showToast("An error occurred", "error");
                    deleteBtn.innerHTML = originalText;
                    deleteBtn.disabled = false;
                });
        }

        // Close quick actions panel when clicking outside
        document.addEventListener('click', function(event) {
            const quickActionsBtn = document.getElementById('quickActionsBtn');
            const quickActionsPanel = document.getElementById('quickActionsPanel');
            
            if (quickActionsVisible && 
                !quickActionsBtn.contains(event.target) && 
                !quickActionsPanel.contains(event.target)) {
                toggleQuickActions();
            }
        });

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const modals = ['addModal', 'editModal', 'viewModal', 'stockModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && !modal.classList.contains('hidden')) {
                    if (event.target === modal) {
                        closeModal(modalId);
                    }
                }
            });
        });
    </script>
</body>
</html>