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
    <div class="mt-6 flex items-center justify-between">
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
// HANDLE ADD CATEGORY
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "add") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // Check if category name already exists
    $check = mysqli_query($conn, "SELECT id FROM categories WHERE name = '$name'");
    if (mysqli_num_rows($check) > 0) {
        exit("error: Category name already exists");
    }

    $query = "INSERT INTO categories (name, description) VALUES ('$name', '$description')";

    if (mysqli_query($conn, $query)) {
        exit("success");
    } else {
        exit("error: " . mysqli_error($conn));
    }
}

// ------------------------------
// HANDLE EDIT CATEGORY
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "edit") {
    $id = $_POST['id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // Check if category name already exists for another category
    $check = mysqli_query($conn, "SELECT id FROM categories WHERE name = '$name' AND id != $id");
    if (mysqli_num_rows($check) > 0) {
        exit("error: Category name already exists");
    }

    $query = "UPDATE categories SET name='$name', description='$description' WHERE id=$id";

    if (mysqli_query($conn, $query)) {
        exit("success");
    } else {
        exit("error: " . mysqli_error($conn));
    }
}

// ------------------------------
// HANDLE DELETE CATEGORY
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "delete") {
    $id = $_POST['id'];

    // Check if category has products
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE category_id=$id");
    $result = mysqli_fetch_assoc($check);

    if ($result['count'] > 0) {
        exit("error: Cannot delete category with existing products");
    }

    mysqli_query($conn, "DELETE FROM categories WHERE id=$id");
    exit("success");
}

// ------------------------------
// HANDLE FETCH CATEGORY DETAILS FOR VIEW MODAL
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "get_category_details") {
    $id = $_POST['id'];

    $query = mysqli_query($conn, "
        SELECT c.*, 
        COUNT(p.id) as product_count,
        COALESCE(SUM(p.quantity), 0) as total_stock,
        COALESCE(SUM(p.price * p.quantity), 0) as total_value,
        (SELECT COUNT(*) FROM products WHERE category_id = c.id AND quantity = 0) as out_of_stock,
        (SELECT COUNT(*) FROM products WHERE category_id = c.id AND quantity < 10 AND quantity > 0) as low_stock
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id 
        WHERE c.id = $id
        GROUP BY c.id
    ");

    if ($row = mysqli_fetch_assoc($query)) {
        // Get products in this category
        $products_query = mysqli_query($conn, "
            SELECT name, sku, quantity, price 
            FROM products 
            WHERE category_id = $id 
            ORDER BY name 
            LIMIT 10
        ");

        $products = [];
        while ($product = mysqli_fetch_assoc($products_query)) {
            $products[] = $product;
        }

        $row['products'] = $products;
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Category not found']);
    }
    exit;
}

// ------------------------------
// HANDLE EXPORT CATEGORIES
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "export") {
    $format = $_POST['format'] ?? 'csv';
    $filter = $_POST['filter'] ?? 'all';

    // Build query based on filter
    $where = "";
    if ($filter == 'with_products') {
        $where = "HAVING product_count > 0";
    } elseif ($filter == 'empty') {
        $where = "HAVING product_count = 0";
    }

    $query = mysqli_query($conn, "
        SELECT c.name, c.description, 
        COUNT(p.id) as product_count,
        COALESCE(SUM(p.quantity), 0) as total_stock,
        COALESCE(SUM(p.price * p.quantity), 0) as total_value
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id 
        GROUP BY c.id 
        $where
        ORDER BY c.name
    ");

    $categories = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $categories[] = $row;
    }

    if ($format == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="categories_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Description', 'Products', 'Total Stock', 'Total Value (₵)']);

        foreach ($categories as $category) {
            fputcsv($output, [
                $category['name'],
                $category['description'],
                $category['product_count'],
                $category['total_stock'],
                '₵' . number_format($category['total_value'], 2)
            ]);
        }
        fclose($output);
        exit;
    } elseif ($format == 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="categories_export_' . date('Y-m-d') . '.json"');
        echo json_encode($categories, JSON_PRETTY_PRINT);
        exit;
    }
}

// ------------------------------
// FETCH CATEGORIES WITH PRODUCT COUNT AND STATS
// ------------------------------
$categories_per_page = 12;
$categories_page = getPageNumber('categories_page');
$categories_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM categories"))['count'];
$categories_total_pages = max(1, (int)ceil($categories_total / $categories_per_page));
if ($categories_page > $categories_total_pages) {
    $categories_page = $categories_total_pages;
}
$categories_offset = ($categories_page - 1) * $categories_per_page;

$categories = mysqli_query($conn, "
    SELECT c.*, 
    COUNT(p.id) as product_count,
    COALESCE(SUM(p.quantity), 0) as total_stock
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.name
    LIMIT $categories_per_page OFFSET $categories_offset
");
?>

<!DOCTYPE html>
<html>

<head>
    <title>Categories - Inventory System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .transition-margin {
            transition: margin-left 0.3s ease-in-out;
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

        /* Modal animations */
        .modal-enter {
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.98);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Card hover effect */
        .category-card {
            transition: all 0.2s ease;
            border: 1px solid #e5e7eb;
        }

        .category-card:hover {
            border-color: #9ca3af;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
            max-height: 200px;
            margin-bottom: 1.5rem;
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

        /* Search highlight */
        .search-highlight {
            background-color: #fef3c7;
            transition: background-color 0.3s ease;
        }
    </style>
</head>

<body class="bg-gray-50 flex">
    <!-- ================= SIDEBAR ================= -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- ================= MAIN CONTENT ================= -->
    <div id="mainContent" class="flex-1 lg:ml-72 p-4 md:p-6 lg:p-8 transition-margin">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1">Categories</h1>
                    <p class="text-gray-500">Organize and manage your product categories</p>
                </div>
                <div class="mt-4 md:mt-0 flex items-center space-x-3">
                    <!-- Quick Actions Button -->
                    <div class="relative">
                        <button id="quickActionsBtn" onclick="toggleQuickActions()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 transition-colors duration-200 flex items-center text-sm font-medium">
                            <i class="fas fa-bolt mr-2 text-yellow-500"></i>
                            Quick Actions
                            <i class="fas fa-chevron-down ml-2 text-xs" id="quickActionsIcon"></i>
                        </button>
                    </div>

                    <button onclick="openModal('addModal')" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>
                        New Category
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Actions Panel (Hidden by default) -->
        <div id="quickActionsPanel" class="quick-actions-panel hidden-panel bg-white rounded-lg border border-gray-200 shadow-sm mb-6">
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            <i class="fas fa-search mr-1"></i> Search Categories
                        </label>
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search by name or description..."
                                class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                onkeyup="filterCategories()">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                            <button onclick="clearSearch()" id="clearSearchBtn" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 hidden">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            <i class="fas fa-filter mr-1"></i> Filter By
                        </label>
                        <select id="filterSelect" onchange="filterCategories()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="all">All Categories</option>
                            <option value="with_products">With Products</option>
                            <option value="empty">Empty Categories</option>
                            <option value="low_stock">Low Stock Categories</option>
                        </select>
                    </div>

                    <!-- Export -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                            <i class="fas fa-download mr-1"></i> Export
                        </label>
                        <div class="flex space-x-2">
                            <select id="exportFilter" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="all">All Categories</option>
                                <option value="with_products">With Products</option>
                                <option value="empty">Empty Categories</option>
                            </select>
                            <button onclick="exportCategories('csv')" class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors duration-200 text-sm" title="Export as CSV">
                                <i class="fas fa-file-csv"></i>
                            </button>
                            <button onclick="exportCategories('json')" class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200 text-sm" title="Export as JSON">
                                <i class="fas fa-file-code"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search Results Info -->
                <div id="searchResultsInfo" class="mt-3 text-xs text-gray-500 hidden">
                    <span id="visibleCount"></span> categories visible out of <span id="totalCount"></span>
                    <button onclick="clearSearch()" class="ml-2 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-times mr-1"></i>Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Categories Grid -->
        <div id="categoriesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (mysqli_num_rows($categories) > 0): ?>
                <?php
                $totalCategories = 0;
                while ($cat = mysqli_fetch_assoc($categories)) {
                    $totalCategories++;
                    $stockStatus = $cat['total_stock'] > 0 ? 'text-green-600' : 'text-gray-400';
                ?>
                    <div class="category-card bg-white rounded-lg overflow-hidden category-item"
                        data-name="<?= strtolower(htmlspecialchars($cat['name'])) ?>"
                        data-description="<?= strtolower(htmlspecialchars($cat['description'])) ?>"
                        data-products="<?= $cat['product_count'] ?>"
                        data-stock="<?= $cat['total_stock'] ?>">
                        <div class="p-5">
                            <!-- Category Header -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600 mr-3">
                                        <i class="fas fa-folder text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 category-name"><?= htmlspecialchars($cat['name']) ?></h3>
                                        <span class="text-xs text-gray-500"><?= $cat['product_count'] ?> products</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <?php if ($cat['description']): ?>
                                <p class="text-sm text-gray-600 mb-3 line-clamp-2 category-description">
                                    <?= htmlspecialchars($cat['description']) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-sm text-gray-400 italic mb-3">No description</p>
                            <?php endif; ?>

                            <!-- Stock Info -->
                            <div class="flex items-center justify-between text-xs border-t border-gray-100 pt-3 mb-3">
                                <span class="text-gray-500">Total Stock:</span>
                                <span class="font-medium <?= $stockStatus ?>"><?= number_format($cat['total_stock']) ?> units</span>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end space-x-2">
                                <button onclick="openViewModal(<?= $cat['id'] ?>)"
                                    class="px-3 py-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors duration-200 text-sm font-medium">
                                    <i class="fas fa-eye mr-1"></i> View
                                </button>
                                <button onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cat['description'], ENT_QUOTES) ?>')"
                                    class="px-3 py-1.5 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-md transition-colors duration-200 text-sm font-medium">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </button>
                                <?php if ($cat['product_count'] == 0): ?>
                                    <button onclick="openDeleteModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')"
                                        class="px-3 py-1.5 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors duration-200 text-sm font-medium">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            <?php else: ?>
                <div class="col-span-3">
                    <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-tags text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Categories Found</h3>
                        <p class="text-gray-500 mb-6">Get started by creating your first category</p>
                        <button onclick="openModal('addModal')" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition-colors duration-200 inline-flex items-center text-sm font-medium">
                            <i class="fas fa-plus mr-2"></i>
                            Add Category
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php renderPagination($categories_page, $categories_total_pages, 'categories_page'); ?>
    </div>

    <!-- Hidden total categories count -->
    <input type="hidden" id="totalCategoriesCount" value="<?= $totalCategories ?? 0 ?>">

    <!-- ================= ADD MODAL ================= -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-96 rounded-lg shadow-xl modal-enter">
            <div class="flex justify-between items-center mb-5">
                <h2 class="text-xl font-semibold text-gray-900">Add Category</h2>
                <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="addForm" onsubmit="event.preventDefault(); submitAdd();">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Category Name
                    </label>
                    <input type="text" name="name" placeholder="e.g., Electronics, Clothing"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        required>
                </div>
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Description <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea name="description" rows="3" placeholder="Enter category description..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm resize-none"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addModal')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors duration-200 text-sm font-medium">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200 text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>
                        Create Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= EDIT MODAL ================= -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-96 rounded-lg shadow-xl modal-enter">
            <div class="flex justify-between items-center mb-5">
                <h2 class="text-xl font-semibold text-gray-900">Edit Category</h2>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editForm" onsubmit="event.preventDefault(); submitEdit();">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Category Name
                    </label>
                    <input type="text" name="name" id="edit_name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        required>
                </div>
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Description
                    </label>
                    <textarea name="description" id="edit_description" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm resize-none"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editModal')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors duration-200 text-sm font-medium">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200 text-sm font-medium">
                        <i class="fas fa-save mr-2"></i>
                        Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= VIEW MODAL ================= -->
    <div id="viewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white w-[550px] rounded-lg shadow-xl modal-enter max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900" id="viewModalTitle">Category Details</h2>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6" id="viewCategoryDetails">
                <!-- Content will be loaded dynamically -->
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-blue-600 border-t-transparent"></div>
                    <p class="mt-2 text-sm text-gray-500">Loading category details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= DELETE CONFIRMATION MODAL ================= -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-96 rounded-lg shadow-xl modal-enter">
            <div class="mb-4">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <h2 class="text-lg font-semibold text-gray-900 text-center mb-2">Delete Category</h2>
                <p class="text-sm text-gray-500 text-center" id="deleteModalMessage">Are you sure you want to delete this category?</p>
                <p class="text-xs text-gray-400 text-center mt-2">This action cannot be undone.</p>
            </div>
            <div class="flex justify-center space-x-3">
                <button onclick="closeModal('deleteModal')"
                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors duration-200 text-sm font-medium flex-1">
                    Cancel
                </button>
                <button onclick="submitDelete()"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors duration-200 text-sm font-medium flex-1">
                    <i class="fas fa-trash mr-2"></i>
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
        function filterCategories() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterValue = document.getElementById('filterSelect').value;
            const categories = document.querySelectorAll('.category-item');

            let visibleCount = 0;

            categories.forEach(category => {
                const name = category.dataset.name;
                const description = category.dataset.description || '';
                const products = parseInt(category.dataset.products);
                const stock = parseInt(category.dataset.stock);

                // Check search match
                const searchMatch = searchTerm === '' ||
                    name.includes(searchTerm) ||
                    description.includes(searchTerm);

                // Check filter match
                let filterMatch = true;
                switch (filterValue) {
                    case 'with_products':
                        filterMatch = products > 0;
                        break;
                    case 'empty':
                        filterMatch = products === 0;
                        break;
                    case 'low_stock':
                        filterMatch = stock > 0 && stock < 50;
                        break;
                }

                // Show/hide based on both conditions
                if (searchMatch && filterMatch) {
                    category.style.display = '';
                    visibleCount++;

                    // Highlight search term if present
                    if (searchTerm !== '') {
                        highlightSearchTerm(category, searchTerm);
                    } else {
                        removeHighlight(category);
                    }
                } else {
                    category.style.display = 'none';
                }
            });

            // Update search results info
            updateSearchResultsInfo(visibleCount, categories.length);

            // Show/hide clear button
            const clearBtn = document.getElementById('clearSearchBtn');
            if (searchTerm !== '') {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
        }

        function highlightSearchTerm(category, term) {
            const nameElement = category.querySelector('.category-name');
            const descElement = category.querySelector('.category-description');

            // Remove previous highlights
            removeHighlight(category);

            // Add highlight class if term matches
            if (nameElement && nameElement.textContent.toLowerCase().includes(term)) {
                nameElement.classList.add('search-highlight');
            }

            if (descElement && descElement.textContent.toLowerCase().includes(term)) {
                descElement.classList.add('search-highlight');
            }
        }

        function removeHighlight(category) {
            const highlighted = category.querySelectorAll('.search-highlight');
            highlighted.forEach(el => el.classList.remove('search-highlight'));
        }

        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterSelect').value = 'all';
            filterCategories();
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
        function exportCategories(format) {
            const exportFilter = document.getElementById('exportFilter').value;

            let data = new FormData();
            data.append("action", "export");
            data.append("format", format);
            data.append("filter", exportFilter);

            // Show loading toast
            showToast(`Exporting categories as ${format.toUpperCase()}...`, "info");

            fetch("categories.php", {
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
                    a.download = `categories_export_${new Date().toISOString().split('T')[0]}.${format}`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);

                    showToast(`Categories exported successfully!`, "success");
                })
                .catch(error => {
                    showToast("Error exporting categories", "error");
                });
        }

        // ----------------------------- MODALS -----------------------------
        function openModal(id) {
            document.getElementById(id).classList.remove("hidden");
        }

        function closeModal(id) {
            document.getElementById(id).classList.add("hidden");
        }

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

        // ----------------------------- ADD CATEGORY -----------------------------
        function submitAdd() {
            let form = document.getElementById("addForm");
            let data = new FormData(form);
            data.append("action", "add");

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...';
            submitBtn.disabled = true;

            fetch("categories.php", {
                    method: "POST",
                    body: data
                })
                .then(res => res.text())
                .then(response => {
                    if (response === "success") {
                        closeModal('addModal');
                        showToast("Category created successfully!", "success");
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

        // ----------------------------- EDIT CATEGORY -----------------------------
        function openEditModal(id, name, description) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_description").value = description;
            openModal('editModal');
        }

        function submitEdit() {
            let form = document.getElementById("editForm");
            let data = new FormData(form);
            data.append("action", "edit");

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
            submitBtn.disabled = true;

            fetch("categories.php", {
                    method: "POST",
                    body: data
                })
                .then(res => res.text())
                .then(response => {
                    if (response === "success") {
                        closeModal('editModal');
                        showToast("Category updated successfully!", "success");
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

        // ----------------------------- VIEW CATEGORY -----------------------------
        function openViewModal(id) {
            openModal('viewModal');

            let data = new FormData();
            data.append("action", "get_category_details");
            data.append("id", id);

            fetch("categories.php", {
                    method: "POST",
                    body: data
                })
                .then(res => res.json())
                .then(category => {
                    if (category.error) {
                        document.getElementById('viewCategoryDetails').innerHTML = `
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                                </div>
                                <p class="text-sm text-red-600">${category.error}</p>
                            </div>
                        `;
                        return;
                    }

                    document.getElementById('viewModalTitle').textContent = category.name;

                    // Build products HTML
                    let productsHtml = '';
                    if (category.products && category.products.length > 0) {
                        productsHtml = category.products.map(product => `
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">${escapeHtml(product.name)}</p>
                                    <p class="text-xs text-gray-500 font-mono">${escapeHtml(product.sku)}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium ${product.quantity < 10 ? 'text-red-600' : 'text-gray-900'}">${product.quantity}</p>
                                    <p class="text-xs text-gray-500">₵${parseFloat(product.price).toFixed(2)}</p>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        productsHtml = '<p class="text-sm text-gray-400 italic text-center py-3">No products in this category</p>';
                    }

                    const html = `
                        <div class="space-y-5">
                            <!-- Description -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-700">${category.description ? escapeHtml(category.description) : '<span class="text-gray-400 italic">No description provided</span>'}</p>
                            </div>
                            
                            <!-- Statistics -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-xs text-gray-500 mb-1">Total Products</p>
                                    <p class="text-xl font-semibold text-gray-900">${category.product_count}</p>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-xs text-gray-500 mb-1">Total Stock</p>
                                    <p class="text-xl font-semibold text-gray-900">${category.total_stock}</p>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-xs text-gray-500 mb-1">Low Stock</p>
                                    <p class="text-xl font-semibold text-yellow-600">${category.low_stock || 0}</p>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-xs text-gray-500 mb-1">Out of Stock</p>
                                    <p class="text-xl font-semibold text-red-600">${category.out_of_stock || 0}</p>
                                </div>
                            </div>
                            
                            <!-- Total Value -->
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <p class="text-xs text-blue-600 mb-1">Total Inventory Value</p>
                                <p class="text-2xl font-bold text-blue-700">₵${parseFloat(category.total_value || 0).toFixed(2)}</p>
                            </div>
                            
                            <!-- Products List -->
                            <div>
                                <h3 class="text-sm font-medium text-gray-700 mb-2">Products in this Category</h3>
                                <div class="max-h-48 overflow-y-auto custom-scrollbar pr-2">
                                    ${productsHtml}
                                </div>
                                ${category.products && category.products.length >= 10 ? '<p class="text-xs text-gray-400 mt-2">Showing first 10 products</p>' : ''}
                            </div>
                        </div>
                    `;

                    document.getElementById('viewCategoryDetails').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('viewCategoryDetails').innerHTML = `
                        <div class="text-center py-8">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                            </div>
                            <p class="text-sm text-red-600">Error loading category details</p>
                        </div>
                    `;
                });
        }

        // ----------------------------- DELETE CATEGORY -----------------------------
        let deleteID = null;
        let deleteName = '';

        function openDeleteModal(id, name) {
            deleteID = id;
            deleteName = name;
            document.getElementById('deleteModalMessage').textContent = `Are you sure you want to delete "${name}"?`;
            openModal('deleteModal');
        }

        function submitDelete() {
            let data = new FormData();
            data.append("action", "delete");
            data.append("id", deleteID);

            const deleteBtn = document.querySelector('#deleteModal button[onclick="submitDelete()"]');
            const originalText = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
            deleteBtn.disabled = true;

            fetch("categories.php", {
                    method: "POST",
                    body: data
                })
                .then(res => res.text())
                .then(response => {
                    if (response === "success") {
                        closeModal('deleteModal');
                        showToast(`Category "${deleteName}" deleted successfully!`, "success");
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.replace("error: ", ""), "error");
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

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const modals = ['addModal', 'editModal', 'viewModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && !modal.classList.contains('hidden')) {
                    if (event.target === modal) {
                        closeModal(modalId);
                    }
                }
            });
        });

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
    </script>
</body>

</html>