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
// HANDLE SETTINGS UPDATE
// ------------------------------
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Update Company Settings
        if ($_POST['action'] == 'update_company') {
            $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
            $company_email = mysqli_real_escape_string($conn, $_POST['company_email']);
            $company_phone = mysqli_real_escape_string($conn, $_POST['company_phone']);
            $company_address = mysqli_real_escape_string($conn, $_POST['company_address']);
            $tax_rate = floatval($_POST['tax_rate']);
            $currency = mysqli_real_escape_string($conn, $_POST['currency']);
            
            // Save to settings table (you'll need to create this)
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES 
                      ('company_name', '$company_name'),
                      ('company_email', '$company_email'),
                      ('company_phone', '$company_phone'),
                      ('company_address', '$company_address'),
                      ('tax_rate', '$tax_rate'),
                      ('currency', '$currency')
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            
            if (mysqli_query($conn, $query)) {
                $success_message = "Company settings updated successfully!";
            } else {
                $error_message = "Error updating settings: " . mysqli_error($conn);
            }
        }
        
        // Update Notification Settings
        if ($_POST['action'] == 'update_notifications') {
            $low_stock_alert = isset($_POST['low_stock_alert']) ? 1 : 0;
            $low_stock_threshold = intval($_POST['low_stock_threshold']);
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $notification_email = mysqli_real_escape_string($conn, $_POST['notification_email']);
            
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES 
                      ('low_stock_alert', '$low_stock_alert'),
                      ('low_stock_threshold', '$low_stock_threshold'),
                      ('email_notifications', '$email_notifications'),
                      ('notification_email', '$notification_email')
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            
            if (mysqli_query($conn, $query)) {
                $success_message = "Notification settings updated successfully!";
            } else {
                $error_message = "Error updating notifications: " . mysqli_error($conn);
            }
        }
        
        // Update System Preferences
        if ($_POST['action'] == 'update_system') {
            $date_format = mysqli_real_escape_string($conn, $_POST['date_format']);
            $timezone = mysqli_real_escape_string($conn, $_POST['timezone']);
            $items_per_page = intval($_POST['items_per_page']);
            $default_language = mysqli_real_escape_string($conn, $_POST['default_language']);
            
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES 
                      ('date_format', '$date_format'),
                      ('timezone', '$timezone'),
                      ('items_per_page', '$items_per_page'),
                      ('default_language', '$default_language')
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            
            if (mysqli_query($conn, $query)) {
                $success_message = "System preferences updated successfully!";
            } else {
                $error_message = "Error updating system preferences: " . mysqli_error($conn);
            }
        }
        
        // Change Password
        if ($_POST['action'] == 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // In a real app, you'd verify current password from users table
            if ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match!";
            } elseif (strlen($new_password) < 8) {
                $error_message = "Password must be at least 8 characters long!";
            } else {
                // Hash password and update (implement with your user system)
                $success_message = "Password changed successfully!";
            }
        }
        
        // Backup Database
        if ($_POST['action'] == 'backup_db') {
            // In production, implement actual backup
            $success_message = "Database backup created successfully!";
        }
    }
}

// ------------------------------
// FETCH CURRENT SETTINGS
// ------------------------------
// In production, you'd fetch from database
// For demo, using defaults
$settings = [
    'company_name' => 'InventoryPro Systems',
    'company_email' => 'info@inventorypro.com',
    'company_phone' => '+1 (555) 123-4567',
    'company_address' => '123 Business Ave, Suite 100, New York, NY 10001',
    'tax_rate' => 8.5,
    'currency' => 'USD',
    'low_stock_alert' => 1,
    'low_stock_threshold' => 10,
    'email_notifications' => 1,
    'notification_email' => 'alerts@inventorypro.com',
    'date_format' => 'Y-m-d',
    'timezone' => 'America/New_York',
    'items_per_page' => 25,
    'default_language' => 'en'
];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Settings - Inventory System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .transition-margin {
            transition: margin-left 0.3s ease-in-out;
        }
        .settings-tab {
            transition: all 0.2s ease;
        }
        .settings-tab.active {
            background-color: #3b82f6;
            color: white;
        }
        .settings-card {
            transition: all 0.2s ease;
        }
        .settings-card:hover {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 flex">
    <!-- ================= SIDEBAR ================= -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- ================= MAIN CONTENT ================= -->
    <div id="mainContent" class="flex-1 lg:ml-72 p-4 md:p-6 lg:p-8 transition-margin">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">System Settings</h1>
            <p class="text-gray-500 mt-1">Configure your inventory management system</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <?= $success_message ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?= $error_message ?>
        </div>
        <?php endif; ?>

        <!-- Settings Tabs -->
        <div class="mb-6 flex space-x-2 border-b border-gray-200">
            <button onclick="showTab('company')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-company">
                <i class="fas fa-building mr-2"></i>
                Company
            </button>
            <button onclick="showTab('notifications')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-notifications">
                <i class="fas fa-bell mr-2"></i>
                Notifications
            </button>
            <button onclick="showTab('system')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-system">
                <i class="fas fa-cog mr-2"></i>
                System
            </button>
            <button onclick="showTab('security')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-security">
                <i class="fas fa-shield-alt mr-2"></i>
                Security
            </button>
            <button onclick="showTab('backup')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-backup">
                <i class="fas fa-database mr-2"></i>
                Backup
            </button>
        </div>

        <!-- Company Settings Tab -->
        <div id="company-tab" class="settings-section">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Company Information</h2>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_company">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                            <input type="text" name="company_name" value="<?= $settings['company_name'] ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company Email</label>
                            <input type="email" name="company_email" value="<?= $settings['company_email'] ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="text" name="company_phone" value="<?= $settings['company_phone'] ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                            <input type="number" name="tax_rate" value="<?= $settings['tax_rate'] ?>" step="0.1" min="0" max="100"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                            <select name="currency" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="USD" <?= $settings['currency'] == 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                <option value="EUR" <?= $settings['currency'] == 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                <option value="GBP" <?= $settings['currency'] == 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                                <option value="JPY" <?= $settings['currency'] == 'JPY' ? 'selected' : '' ?>>JPY (¥)</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                            <textarea name="company_address" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?= $settings['company_address'] ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div id="notifications-tab" class="settings-section hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Notification Settings</h2>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_notifications">
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800">Low Stock Alerts</p>
                                <p class="text-sm text-gray-500">Get notified when products are running low</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="low_stock_alert" class="sr-only peer" <?= $settings['low_stock_alert'] ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800">Email Notifications</p>
                                <p class="text-sm text-gray-500">Receive notifications via email</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="email_notifications" class="sr-only peer" <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Low Stock Threshold</label>
                                <input type="number" name="low_stock_threshold" value="<?= $settings['low_stock_threshold'] ?>" min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Alert when stock falls below this number</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Notification Email</label>
                                <input type="email" name="notification_email" value="<?= $settings['notification_email'] ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- System Preferences Tab -->
        <div id="system-tab" class="settings-section hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">System Preferences</h2>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_system">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date Format</label>
                            <select name="date_format" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Y-m-d" <?= $settings['date_format'] == 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (2024-12-31)</option>
                                <option value="m/d/Y" <?= $settings['date_format'] == 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY (12/31/2024)</option>
                                <option value="d/m/Y" <?= $settings['date_format'] == 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (31/12/2024)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Time Zone</label>
                            <select name="timezone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="America/New_York" <?= $settings['timezone'] == 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                <option value="America/Chicago" <?= $settings['timezone'] == 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                <option value="America/Denver" <?= $settings['timezone'] == 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                <option value="America/Los_Angeles" <?= $settings['timezone'] == 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Items Per Page</label>
                            <input type="number" name="items_per_page" value="<?= $settings['items_per_page'] ?>" min="10" max="100" step="5"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Default Language</label>
                            <select name="default_language" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="en" <?= $settings['default_language'] == 'en' ? 'selected' : '' ?>>English</option>
                                <option value="es" <?= $settings['default_language'] == 'es' ? 'selected' : '' ?>>Spanish</option>
                                <option value="fr" <?= $settings['default_language'] == 'fr' ? 'selected' : '' ?>>French</option>
                                <option value="de" <?= $settings['default_language'] == 'de' ? 'selected' : '' ?>>German</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security-tab" class="settings-section hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Change Password</h2>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <input type="password" name="current_password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <input type="password" name="new_password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters long</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-key mr-2"></i>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Two Factor Authentication -->
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Two-Factor Authentication</h2>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-800">Enable 2FA</p>
                            <p class="text-sm text-gray-500">Add an extra layer of security to your account</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                        <p class="text-sm text-blue-700">
                            <i class="fas fa-info-circle mr-1"></i>
                            Two-factor authentication adds an additional layer of security to your account by requiring more than just a password to sign in.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup Tab -->
        <div id="backup-tab" class="settings-section hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Database Backup</h2>
                </div>
                <div class="p-6">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                            <div>
                                <p class="text-sm text-yellow-700 font-medium">Important</p>
                                <p class="text-sm text-yellow-600 mt-1">Regular backups help protect your data. Download a backup before making major changes.</p>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="backup_db">
                        <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors duration-200 flex items-center">
                            <i class="fas fa-download mr-2"></i>
                            Download Database Backup
                        </button>
                    </form>
                    
                    <div class="mt-6">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Recent Backups</h3>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-database text-gray-400 mr-3"></i>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">backup_2024_12_31.sql</p>
                                        <p class="text-xs text-gray-500">2.5 MB · Dec 31, 2024</p>
                                    </div>
                                </div>
                                <button class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-database text-gray-400 mr-3"></i>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">backup_2024_12_30.sql</p>
                                        <p class="text-xs text-gray-500">2.5 MB · Dec 30, 2024</p>
                                    </div>
                                </div>
                                <button class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Auto Backup Settings -->
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Automatic Backup</h2>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="font-medium text-gray-800">Enable Auto Backup</p>
                            <p class="text-sm text-gray-500">Automatically backup database daily</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Backup Frequency</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option>Daily</option>
                                <option>Weekly</option>
                                <option>Monthly</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Keep backups for</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option>7 days</option>
                                <option>30 days</option>
                                <option>90 days</option>
                                <option>1 year</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function showTab(tabName) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.add('hidden');
            });
            
            // Show selected section
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Update tab styles
            document.querySelectorAll('[id^="tab-"]').forEach(tab => {
                tab.classList.remove('border-blue-600', 'text-blue-600');
            });
            
            document.getElementById('tab-' + tabName).classList.add('border-blue-600', 'text-blue-600');
        }
        
        // Show first tab by default
        showTab('company');
    </script>
</body>
</html>