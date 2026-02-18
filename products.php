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
    </style>
</head>
<body class="bg-gray-100 flex">
    <!-- ================= SIDEBAR ================= -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- ================= MAIN CONTENT ================= -->
    <div id="mainContent" class="flex-1 lg:ml-72 p-4 md:p-6 lg:p-8 transition-margin">
        <!-- Header with actions -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">Products</h1>
                <p class="text-gray-600">Manage your inventory products</p>
            </div>
            <div class="mt-4 md:mt-0 flex space-x-3">
                <a href="categories.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-tags mr-2"></i>
                    Manage Categories
                </a>
                <button onclick="openModal('addModal')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Add Product
                </button>
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Product List</h2>
                <span class="text-sm text-gray-500">Total: <?= mysqli_num_rows($products) ?> products</span>
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
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if(mysqli_num_rows($products) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($products)) { ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600"><?= htmlspecialchars($row['sku']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                    <button onclick="openEditModal(
                                        <?= $row['id'] ?>,
                                        '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['sku'], ENT_QUOTES) ?>',
                                        <?= $row['price'] ?>,
                                        <?= $row['category_id'] ?: 'null' ?>
                                    )" class="text-blue-600 hover:text-blue-900 mr-3 transition-colors duration-200">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="openDeleteModal(<?= $row['id'] ?>)" class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                        <i class="fas fa-trash"></i> Delete
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

    <!-- ================= TOAST ================= -->
    <div id="toast" class="fixed top-5 right-5 hidden z-50"></div>

    <script>
        // ----------------------------- MODALS -----------------------------
        function openModal(id){ document.getElementById(id).classList.remove("hidden"); }
        function closeModal(id){ document.getElementById(id).classList.add("hidden"); }

        // ----------------------------- TOAST -----------------------------
        function showToast(msg, type = "success"){
            const t = document.getElementById("toast");
            t.innerText = msg;
            t.className = `fixed top-5 right-5 px-4 py-2 rounded shadow z-50 ${
                type === "success" ? "bg-green-600" : "bg-red-600"
            } text-white`;
            t.classList.remove("hidden");
            setTimeout(() => t.classList.add("hidden"), 3000);
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
            // This will run when page loads, but we want it when modal opens
            const addModal = document.getElementById('addModal');
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        if (!addModal.classList.contains('hidden')) {
                            // Modal just opened
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
            fetch("products.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('addModal'); 
                        showToast("Product Added Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.replace("error: ", ""), "error");
                    }
                });
        }

        // ----------------------------- EDIT PRODUCT -----------------------------
        function openEditModal(id, name, sku, price, category_id){
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_sku").value = sku;
            document.getElementById("edit_price").value = price;
            
            // Set category dropdown
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
            fetch("products.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('editModal'); 
                        showToast("Product Updated Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.replace("error: ", ""), "error");
                    }
                });
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
            
            // Validate if removing stock
            let changeType = document.getElementById("change_type").value;
            let quantity = parseInt(document.getElementById("stock_quantity").value);
            let currentStock = parseInt(document.getElementById("current_stock").innerText);
            
            if(changeType === "OUT" && quantity > currentStock) {
                showToast("Not enough stock available!", "error");
                return;
            }
            
            fetch("products.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('stockModal'); 
                        showToast("Stock Updated Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast("Error: " + response, "error");
                    }
                });
        }

        // ----------------------------- DELETE PRODUCT -----------------------------
        let deleteID = null;
        function openDeleteModal(id){ deleteID = id; openModal('deleteModal'); }
        
        function submitDelete(){
            let data = new FormData();
            data.append("action","delete");
            data.append("id", deleteID);
            fetch("products.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('deleteModal'); 
                        showToast("Product Deleted Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast("Error: " + response, "error");
                    }
                });
        }
    </script>
</body>
</html>