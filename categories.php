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
// HANDLE ADD CATEGORY
// ------------------------------
if (isset($_POST['action']) && $_POST['action'] == "add") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $query = "INSERT INTO categories (name, description) VALUES ('$name', '$description')";
    
    if(mysqli_query($conn, $query)) {
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
    
    $query = "UPDATE categories SET name='$name', description='$description' WHERE id=$id";
    
    if(mysqli_query($conn, $query)) {
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
    
    if($result['count'] > 0) {
        exit("error: Cannot delete category with existing products");
    }
    
    mysqli_query($conn, "DELETE FROM categories WHERE id=$id");
    exit("success");
}

// ------------------------------
// FETCH CATEGORIES WITH PRODUCT COUNT
// ------------------------------
$categories = mysqli_query($conn, "
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.name
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
    </style>
</head>
<body class="bg-gray-100 flex">
    <!-- ================= SIDEBAR ================= -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- ================= MAIN CONTENT ================= -->
    <div id="mainContent" class="flex-1 lg:ml-72 p-4 md:p-6 lg:p-8 transition-margin">
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">Categories</h1>
                <p class="text-gray-600">Organize your products with categories</p>
            </div>
            <button onclick="openModal('addModal')" class="mt-4 md:mt-0 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors duration-200 flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Add Category
            </button>
        </div>

        <!-- Categories Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if(mysqli_num_rows($categories) > 0): ?>
                <?php while($cat = mysqli_fetch_assoc($categories)) { ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($cat['name']) ?></h3>
                            <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                                <?= $cat['product_count'] ?> products
                            </span>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">
                            <?= htmlspecialchars($cat['description'] ?: 'No description') ?>
                        </p>
                        <div class="flex justify-end space-x-2">
                            <button onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cat['description'], ENT_QUOTES) ?>')" 
                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if($cat['product_count'] == 0): ?>
                            <button onclick="openDeleteModal(<?= $cat['id'] ?>)" 
                                    class="text-red-600 hover:text-red-800 text-sm ml-3">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            <?php else: ?>
                <div class="col-span-3 text-center py-12 bg-white rounded-lg">
                    <i class="fas fa-tags text-5xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No categories found. Click "Add Category" to create one.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================= ADD MODAL ================= -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-96 rounded-lg shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Add Category</h2>
                <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="addForm" onsubmit="event.preventDefault(); submitAdd();">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                    <input type="text" name="name" placeholder="e.g., Electronics" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Enter category description" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200">
                        Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= EDIT MODAL ================= -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white p-6 w-96 rounded-lg shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Edit Category</h2>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="editForm" onsubmit="event.preventDefault(); submitEdit();">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                    <input type="text" name="name" id="edit_name" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="edit_description" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200">
                        Update Category
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
                <h2 class="text-xl font-bold text-gray-800 mb-2">Delete Category?</h2>
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
        function openModal(id){ document.getElementById(id).classList.remove("hidden"); }
        function closeModal(id){ document.getElementById(id).classList.add("hidden"); }

        function showToast(msg, type = "success"){
            const t = document.getElementById("toast");
            t.innerText = msg;
            t.className = `fixed top-5 right-5 px-4 py-2 rounded shadow z-50 ${
                type === "success" ? "bg-green-600" : "bg-red-600"
            } text-white`;
            t.classList.remove("hidden");
            setTimeout(() => t.classList.add("hidden"), 3000);
        }

        // Add Category
        function submitAdd(){
            let form = document.getElementById("addForm");
            let data = new FormData(form);
            data.append("action","add");
            fetch("categories.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('addModal'); 
                        showToast("Category Added Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast("Error: " + response, "error");
                    }
                });
        }

        // Edit Category
        function openEditModal(id, name, description){
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_description").value = description;
            openModal('editModal');
        }
        
        function submitEdit(){
            let form = document.getElementById("editForm");
            let data = new FormData(form);
            data.append("action","edit");
            fetch("categories.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('editModal'); 
                        showToast("Category Updated Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast("Error: " + response, "error");
                    }
                });
        }

        // Delete Category
        let deleteID = null;
        function openDeleteModal(id){ deleteID = id; openModal('deleteModal'); }
        
        function submitDelete(){
            let data = new FormData();
            data.append("action","delete");
            data.append("id", deleteID);
            fetch("categories.php", { method:"POST", body:data })
                .then(res => res.text())
                .then(response => {
                    if(response === "success") {
                        closeModal('deleteModal'); 
                        showToast("Category Deleted Successfully!"); 
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast("Error: " + response, "error");
                    }
                });
        }
    </script>
</body>
</html>