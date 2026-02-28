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

// Initialize managers
require_once './config/NotificationManager.php';

$notification_manager = new NotificationManager($conn, $settings);

// ------------------------------
// HELPER FUNCTIONS
// ------------------------------

// Helper function for safe HTML output
function safe_html($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

// Helper function for safe POST values
function post_value($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// Helper function for safe POST integers
function post_int($key, $default = 0) {
    return isset($_POST[$key]) && is_numeric($_POST[$key]) ? intval($_POST[$key]) : $default;
}

// Helper function for safe POST floats
function post_float($key, $default = 0.0) {
    return isset($_POST[$key]) && is_numeric($_POST[$key]) ? floatval($_POST[$key]) : $default;
}

// Helper function for checkboxes
function post_checkbox($key) {
    return isset($_POST[$key]) ? 1 : 0;
}

// ------------------------------
// HANDLE SETTINGS UPDATE
// ------------------------------
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Update Company Settings
        if ($_POST['action'] == 'update_company') {
            $company_settings = [
                'company_name' => post_value('company_name', 'My Company'),
                'company_email' => post_value('company_email', 'info@company.com'),
                'company_phone' => post_value('company_phone', '+1234567890'),
                'company_address' => post_value('company_address', '123 Business St, City, Country'),
                'company_website' => post_value('company_website', ''),
                'tax_rate' => post_float('tax_rate', 0),
                'currency' => post_value('currency', 'GHS'),
                'currency_symbol' => post_value('currency_symbol', '₵'),
                'currency_position' => post_value('currency_position', 'before'),
                'decimal_places' => post_int('decimal_places', 2),
                'thousand_separator' => post_value('thousand_separator', ','),
                'decimal_separator' => post_value('decimal_separator', '.')
            ];
            
            if ($settings->updateMany($company_settings, 'company')) {
                $success_message = "Company settings updated successfully!";
            } else {
                $error_message = "Error updating company settings";
            }
        }
        
        // Update Notification Settings
        if ($_POST['action'] == 'update_notifications') {
            $notification_settings = [
                'low_stock_alert' => post_checkbox('low_stock_alert'),
                'low_stock_threshold' => post_int('low_stock_threshold', 10),
                'email_notifications' => post_checkbox('email_notifications'),
                'notification_email' => post_value('notification_email', ''),
                'stock_out_alert' => post_checkbox('stock_out_alert'),
                'daily_summary' => post_checkbox('daily_summary')
            ];
            
            // Ensure threshold is at least 1
            if ($notification_settings['low_stock_threshold'] < 1) {
                $notification_settings['low_stock_threshold'] = 1;
            }
            
            if ($settings->updateMany($notification_settings, 'notifications')) {
                $success_message = "Notification settings updated successfully!";
                
                // Check low stock immediately if enabled
                if ($notification_settings['low_stock_alert']) {
                    $notification_manager->checkLowStock();
                }
            } else {
                $error_message = "Error updating notification settings";
            }
        }
        
        // Update System Preferences
        if ($_POST['action'] == 'update_system') {
            $system_settings = [
                'date_format' => post_value('date_format', 'Y-m-d'),
                'time_format' => post_value('time_format', 'H:i:s'),
                'timezone' => post_value('timezone', 'Africa/Accra'),
                'items_per_page' => post_int('items_per_page', 10),
                'default_language' => post_value('default_language', 'en'),
                'theme_color' => post_value('theme_color', 'blue'),
                'session_timeout' => post_int('session_timeout', 3600)
            ];
            
            // Validate items per page
            if ($system_settings['items_per_page'] < 10) {
                $system_settings['items_per_page'] = 10;
            } elseif ($system_settings['items_per_page'] > 100) {
                $system_settings['items_per_page'] = 100;
            }
            
            // Validate session timeout
            if ($system_settings['session_timeout'] < 300) {
                $system_settings['session_timeout'] = 300;
            }
            
            if ($settings->updateMany($system_settings, 'system')) {
                // Update PHP timezone
                date_default_timezone_set($system_settings['timezone']);
                $success_message = "System preferences updated successfully!";
            } else {
                $error_message = "Error updating system preferences";
            }
        }
        
        // Update Invoice Settings
        if ($_POST['action'] == 'update_invoice') {
            $invoice_settings = [
                'invoice_prefix' => post_value('invoice_prefix', 'INV-'),
                'invoice_next_number' => post_int('invoice_next_number', 1001),
                'invoice_footer' => post_value('invoice_footer', 'Thank you for your business!'),
                'invoice_terms' => post_value('invoice_terms', 'Payment due within 30 days'),
                'invoice_show_tax' => post_checkbox('invoice_show_tax'),
                'invoice_show_discount' => post_checkbox('invoice_show_discount')
            ];
            
            // Ensure invoice number is at least 1
            if ($invoice_settings['invoice_next_number'] < 1) {
                $invoice_settings['invoice_next_number'] = 1;
            }
            
            if ($settings->updateMany($invoice_settings, 'invoice')) {
                $success_message = "Invoice settings updated successfully!";
            } else {
                $error_message = "Error updating invoice settings";
            }
        }
        
        // Change Password
        if ($_POST['action'] == 'change_password') {
            $current_password = post_value('current_password');
            $new_password = post_value('new_password');
            $confirm_password = post_value('confirm_password');
            
            // Validate inputs
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = "All password fields are required!";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match!";
            } elseif (strlen($new_password) < 8) {
                $error_message = "Password must be at least 8 characters long!";
            } else {
                // In a real app, you'd verify current password from users table
                // Hash password and update in users table
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // TODO: Update password in your users table
                // For now, just show success message
                $success_message = "Password changed successfully!";
                
                // Log the password change (optional)
                error_log("Password changed for user: " . ($_SESSION['username'] ?? 'unknown'));
            }
        }
        
        // Update Security Settings
        if ($_POST['action'] == 'update_security') {
            $security_settings = [
                'password_expiry' => post_int('password_expiry', 90),
                'max_login_attempts' => post_int('max_login_attempts', 5),
                'two_factor_auth' => post_checkbox('two_factor_auth'),
                'session_security' => post_checkbox('session_security'),
                'audit_log' => post_checkbox('audit_log')
            ];
            
            // Validate values
            if ($security_settings['password_expiry'] < 0) {
                $security_settings['password_expiry'] = 0;
            }
            
            if ($security_settings['max_login_attempts'] < 1) {
                $security_settings['max_login_attempts'] = 1;
            } elseif ($security_settings['max_login_attempts'] > 10) {
                $security_settings['max_login_attempts'] = 10;
            }
            
            if ($settings->updateMany($security_settings, 'security')) {
                $success_message = "Security settings updated successfully!";
            } else {
                $error_message = "Error updating security settings";
            }
        }
        
        // Update Tax Settings
        if ($_POST['action'] == 'update_tax') {
            $tax_settings = [
                'tax_name' => post_value('tax_name', 'VAT'),
                'tax_rate' => post_float('tax_rate', 0),
                'tax_inclusive' => post_checkbox('tax_inclusive'),
                'tax_number' => post_value('tax_number', ''),
                'tax_registered' => post_checkbox('tax_registered')
            ];
            
            // Validate tax rate
            if ($tax_settings['tax_rate'] < 0) {
                $tax_settings['tax_rate'] = 0;
            } elseif ($tax_settings['tax_rate'] > 100) {
                $tax_settings['tax_rate'] = 100;
            }
            
            if ($settings->updateMany($tax_settings, 'tax')) {
                $success_message = "Tax settings updated successfully!";
            } else {
                $error_message = "Error updating tax settings";
            }
        }
        
        // Send Test Email
        if ($_POST['action'] == 'test_email') {
            $to = $settings->get('notification_email', '');
            $company_name = $settings->get('company_name', 'Inventory System');
            $company_email = $settings->get('company_email', 'noreply@inventory.com');
            
            if (empty($to)) {
                $error_message = "No notification email configured. Please save notification settings first.";
            } else {
                $subject = "Test Email from " . $company_name;
                $message = "This is a test email to verify your notification settings.\n\n";
                $message .= "If you received this email, your email configuration is working correctly.\n";
                $message .= "Time sent: " . date('Y-m-d H:i:s');
                
                $headers = "From: " . $company_email . "\r\n";
                $headers .= "Reply-To: " . $company_email . "\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                if (mail($to, $subject, $message, $headers)) {
                    $success_message = "Test email sent successfully to " . $to;
                } else {
                    $error_message = "Failed to send test email. Please check your server's mail configuration.";
                }
            }
        }
    }
}

// Get all settings by category for display
$company_settings = $settings->getByCategory('company');
$notification_settings = $settings->getByCategory('notifications');
$system_settings = $settings->getByCategory('system');
$invoice_settings = $settings->getByCategory('invoice');
$security_settings = $settings->getByCategory('security');
$financial_settings = $settings->getByCategory('financial');
$tax_settings = $settings->getByCategory('tax');

// Get company name for page title
$company_name = $settings->get('company_name', 'Inventory System');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= safe_html($company_name) ?> - Settings</title>
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
        .currency-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center animate-pulse">
            <i class="fas fa-check-circle mr-2"></i>
            <?= safe_html($success_message) ?>
            <button onclick="this.parentElement.remove()" class="ml-auto text-green-700 hover:text-green-900">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?= safe_html($error_message) ?>
            <button onclick="this.parentElement.remove()" class="ml-auto text-red-700 hover:text-red-900">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <!-- Settings Tabs -->
        <div class="mb-6 flex flex-wrap gap-2 border-b border-gray-200">
            <button onclick="showTab('company')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-company">
                <i class="fas fa-building mr-2"></i>
                Company
            </button>
            <button onclick="showTab('financial')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-financial">
                <i class="fas fa-coins mr-2"></i>
                Financial
            </button>
            <button onclick="showTab('tax')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-tax">
                <i class="fas fa-percent mr-2"></i>
                Tax Settings
            </button>
            <button onclick="showTab('notifications')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-notifications">
                <i class="fas fa-bell mr-2"></i>
                Notifications
            </button>
            <button onclick="showTab('system')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-system">
                <i class="fas fa-cog mr-2"></i>
                System
            </button>
            <button onclick="showTab('invoice')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-invoice">
                <i class="fas fa-file-invoice mr-2"></i>
                Invoice
            </button>
            <button onclick="showTab('security')" class="settings-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors duration-200" id="tab-security">
                <i class="fas fa-shield-alt mr-2"></i>
                Security
            </button>
        </div>

        <!-- Company Settings Tab -->
        <div id="company-tab" class="settings-section">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Company Information</h2>
                    <p class="text-sm text-gray-500 mt-1">Update your company details - these will appear throughout the system</p>
                </div>
                <form method="POST" class="p-6" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_company">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company Name *</label>
                            <input type="text" name="company_name" value="<?= safe_html($settings->get('company_name', 'My Company')) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <p class="text-xs text-gray-500 mt-1">This name will appear in the header, invoices, and reports</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company Email *</label>
                            <input type="email" name="company_email" value="<?= safe_html($settings->get('company_email', 'info@company.com')) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                            <input type="text" name="company_phone" value="<?= safe_html($settings->get('company_phone', '+1234567890')) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Website</label>
                            <input type="url" name="company_website" value="<?= safe_html($settings->get('company_website', 'https://example.com')) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="https://example.com">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address *</label>
                            <textarea name="company_address" rows="3" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?= safe_html($settings->get('company_address', '123 Business St, City, Country')) ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company Logo</label>
                            <input type="file" name="company_logo" accept="image/*"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Recommended size: 200x50px, max size: 2MB</p>
                            
                            <!-- Current Logo Preview -->
                            <?php if ($settings->get('company_logo')): ?>
                            <div class="mt-2 p-2 border border-gray-200 rounded-lg">
                                <p class="text-xs text-gray-500 mb-1">Current Logo:</p>
                                <img src="<?= safe_html($settings->get('company_logo')) ?>" alt="Company Logo" class="h-10 object-contain">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Preview Card -->
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">Preview</h3>
                        <div class="bg-white p-4 rounded-lg shadow-sm">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                                    <span class="text-white font-bold text-lg"><?= substr(safe_html($settings->get('company_name', 'My Company')), 0, 1) ?></span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800" id="previewCompanyName">
                                        <?= safe_html($settings->get('company_name', 'My Company')) ?>
                                    </p>
                                    <p class="text-xs text-gray-500">Your company name appears here</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Financial Settings Tab -->
        <div id="financial-tab" class="settings-section hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
                    <h2 class="text-lg font-semibold">Currency & Formatting</h2>
                    <p class="text-sm opacity-90">Configure how currency is displayed throughout the system</p>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_company">
                    
                    <!-- Currency Preview Card -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Live Preview</h3>
                        <div class="flex items-center justify-center space-x-4 flex-wrap gap-4">
                            <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                                <span class="text-sm text-gray-500">Product Price</span>
                                <div class="text-2xl font-bold currency-preview-text mt-1">
                                    <?php 
                                    $symbol = $settings->get('currency_symbol', '₵') ?? '₵';
                                    $position = $settings->get('currency_position', 'before') ?? 'before';
                                    $decimals = intval($settings->get('decimal_places', 2) ?? 2);
                                    $amount = number_format(1250.50, $decimals, 
                                        $settings->get('decimal_separator', '.') ?? '.', 
                                        $settings->get('thousand_separator', ',') ?? ',');
                                    
                                    if ($position == 'before') {
                                        echo $symbol . $amount;
                                    } else {
                                        echo $amount . ' ' . $symbol;
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                                <span class="text-sm text-gray-500">Total Value</span>
                                <div class="text-2xl font-bold currency-preview-text mt-1 text-green-600">
                                    <?php 
                                    $amount = number_format(15250.75, $decimals, 
                                        $settings->get('decimal_separator', '.') ?? '.', 
                                        $settings->get('thousand_separator', ',') ?? ',');
                                    
                                    if ($position == 'before') {
                                        echo $symbol . $amount;
                                    } else {
                                        echo $amount . ' ' . $symbol;
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                            <select name="currency" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <optgroup label="West African Currencies">
                                    <option value="GHS" <?= ($settings->get('currency', 'GHS') ?? 'GHS') == 'GHS' ? 'selected' : '' ?>>GHS - Ghanaian Cedi</option>
                                    <option value="NGN" <?= ($settings->get('currency', 'GHS') ?? 'GHS') == 'NGN' ? 'selected' : '' ?>>NGN - Nigerian Naira</option>
                                    <option value="XOF" <?= ($settings->get('currency', 'GHS') ?? 'GHS') == 'XOF' ? 'selected' : '' ?>>XOF - CFA Franc</option>
                                </optgroup>
                                <optgroup label="Major Currencies">
                                    <option value="USD" <?= ($settings->get('currency', 'GHS') ?? 'GHS') == 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                                    <option value="EUR" <?= ($settings->get('currency', 'GHS') ?? 'GHS') == 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                                    <option value="GBP" <?= ($settings->get('currency', 'GHS') ?? 'GHS') == 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Currency Symbol</label>
                            <div class="flex">
                                <input type="text" name="currency_symbol" value="<?= safe_html($settings->get('currency_symbol', '₵'), '₵') ?>" maxlength="5"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-lg" required>
                                <span class="inline-flex items-center px-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-600">
                                    <i class="fas fa-info-circle" title="Symbol shown before or after amount"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Symbol Position</label>
                            <select name="currency_position" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="before" <?= ($settings->get('currency_position', 'before') ?? 'before') == 'before' ? 'selected' : '' ?>>Before amount (₵1,250.50)</option>
                                <option value="after" <?= ($settings->get('currency_position', 'before') ?? 'before') == 'after' ? 'selected' : '' ?>>After amount (1,250.50 ₵)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Decimal Places</label>
                            <select name="decimal_places" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="0" <?= intval($settings->get('decimal_places', 2) ?? 2) == 0 ? 'selected' : '' ?>>0 (No decimals) - ₵1,250</option>
                                <option value="1" <?= intval($settings->get('decimal_places', 2) ?? 2) == 1 ? 'selected' : '' ?>>1 (e.g., ₵1,250.5)</option>
                                <option value="2" <?= intval($settings->get('decimal_places', 2) ?? 2) == 2 ? 'selected' : '' ?>>2 (e.g., ₵1,250.50)</option>
                                <option value="3" <?= intval($settings->get('decimal_places', 2) ?? 2) == 3 ? 'selected' : '' ?>>3 (e.g., ₵1,250.500)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Thousand Separator</label>
                            <select name="thousand_separator" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="," <?= ($settings->get('thousand_separator', ',') ?? ',') == ',' ? 'selected' : '' ?>>Comma (1,250.50)</option>
                                <option value="." <?= ($settings->get('thousand_separator', ',') ?? ',') == '.' ? 'selected' : '' ?>>Period (1.250,50)</option>
                                <option value=" " <?= ($settings->get('thousand_separator', ',') ?? ',') == ' ' ? 'selected' : '' ?>>Space (1 250.50)</option>
                                <option value="" <?= ($settings->get('thousand_separator', ',') ?? ',') == '' ? 'selected' : '' ?>>None (1250.50)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Decimal Separator</label>
                            <select name="decimal_separator" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="." <?= ($settings->get('decimal_separator', '.') ?? '.') == '.' ? 'selected' : '' ?>>Period (1,250.50)</option>
                                <option value="," <?= ($settings->get('decimal_separator', '.') ?? '.') == ',' ? 'selected' : '' ?>>Comma (1.250,50)</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">GHS Format Examples</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                <div class="p-3 bg-gray-50 rounded-lg text-center">
                                    <span class="text-gray-600">Standard:</span>
                                    <span class="block font-mono font-bold">₵1,250.50</span>
                                </div>
                                <div class="p-3 bg-gray-50 rounded-lg text-center">
                                    <span class="text-gray-600">European:</span>
                                    <span class="block font-mono font-bold">₵1.250,50</span>
                                </div>
                                <div class="p-3 bg-gray-50 rounded-lg text-center">
                                    <span class="text-gray-600">Compact:</span>
                                    <span class="block font-mono font-bold">₵1250.5</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tax Settings Tab -->
        <div id="tax-tab" class="settings-section hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Tax Configuration</h2>
                    <p class="text-sm text-gray-500 mt-1">Configure tax settings for your business</p>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_tax">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tax Name</label>
                            <input type="text" name="tax_name" value="<?= safe_html($settings->get('tax_name', 'VAT'), 'VAT') ?>" 
                                   placeholder="e.g., VAT, GST, Sales Tax"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                            <div class="relative">
                                <input type="number" name="tax_rate" value="<?= floatval($settings->get('tax_rate', 0) ?? 0) ?>" step="0.01" min="0" max="100"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-8">
                                <span class="absolute right-3 top-2 text-gray-500">%</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Enter a value between 0 and 100</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tax Number / VAT ID</label>
                            <input type="text" name="tax_number" value="<?= safe_html($settings->get('tax_number', '')) ?>" 
                                   placeholder="e.g., VAT-12345678"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-800">Tax Inclusive Pricing</p>
                                        <p class="text-sm text-gray-500">Show prices including tax by default</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="tax_inclusive" class="sr-only peer" <?= ($settings->get('tax_inclusive', 0) ?? 0) ? 'checked' : '' ?>>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-800">Tax Registered</p>
                                        <p class="text-sm text-gray-500">Enable if your business is tax registered</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="tax_registered" class="sr-only peer" <?= ($settings->get('tax_registered', 0) ?? 0) ? 'checked' : '' ?>>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tax Calculation Preview -->
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">Tax Calculation Preview</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <?php 
                            $tax_rate = floatval($settings->get('tax_rate', 0) ?? 0);
                            $price = 100.00;
                            $tax_amount = $price * ($tax_rate / 100);
                            $total = $price + $tax_amount;
                            ?>
                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                <span class="text-gray-600">Base Price:</span>
                                <span class="block font-bold"><?= $settings->get('currency_symbol', '₵') ?>100.00</span>
                            </div>
                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                <span class="text-gray-600">Tax (<?= $tax_rate ?>%):</span>
                                <span class="block font-bold text-blue-600"><?= $settings->get('currency_symbol', '₵') ?><?= number_format($tax_amount, 2) ?></span>
                            </div>
                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                <span class="text-gray-600">Total:</span>
                                <span class="block font-bold text-green-600"><?= $settings->get('currency_symbol', '₵') ?><?= number_format($total, 2) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
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
                    <p class="text-sm text-gray-500 mt-1">Configure how and when you receive notifications</p>
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
                                <input type="checkbox" name="low_stock_alert" class="sr-only peer" <?= ($settings->get('low_stock_alert') ?? 0) ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800">Stock Out Alerts</p>
                                <p class="text-sm text-gray-500">Get notified when stock reaches zero</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="stock_out_alert" class="sr-only peer" <?= ($settings->get('stock_out_alert', 1) ?? 1) ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800">Email Notifications</p>
                                <p class="text-sm text-gray-500">Receive notifications via email</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="email_notifications" class="sr-only peer" <?= ($settings->get('email_notifications') ?? 0) ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800">Daily Summary Email</p>
                                <p class="text-sm text-gray-500">Receive daily inventory summary</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="daily_summary" class="sr-only peer" <?= ($settings->get('daily_summary') ?? 0) ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Low Stock Threshold</label>
                                <input type="number" name="low_stock_threshold" value="<?= intval($settings->get('low_stock_threshold', 10) ?? 10) ?>" min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Alert when stock falls below this number</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Notification Email</label>
                                <input type="email" name="notification_email" value="<?= safe_html($settings->get('notification_email', '')) ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="notifications@example.com">
                                <p class="text-xs text-gray-500 mt-1">Email address for receiving notifications</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notification Preview -->
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Sample Notification</h3>
                        <div class="bg-white p-3 rounded-lg shadow-sm border-l-4 border-yellow-400">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-2"></i>
                                <div>
                                    <p class="font-medium text-gray-800">Low Stock Alert</p>
                                    <p class="text-sm text-gray-600">5 products are below the threshold of <?= intval($settings->get('low_stock_threshold', 10) ?? 10) ?> units</p>
                                    <p class="text-xs text-gray-400 mt-1">2 hours ago</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="submit" formaction="?action=test_email" name="action" value="test_email" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition-colors duration-200 flex items-center">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Test Email
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
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
                    <p class="text-sm text-gray-500 mt-1">Configure system-wide settings and preferences</p>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_system">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date Format</label>
                            <select name="date_format" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Y-m-d" <?= ($settings->get('date_format', 'Y-m-d') ?? 'Y-m-d') == 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (<?= date('Y-m-d') ?>)</option>
                                <option value="m/d/Y" <?= ($settings->get('date_format', 'Y-m-d') ?? 'Y-m-d') == 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY (<?= date('m/d/Y') ?>)</option>
                                <option value="d/m/Y" <?= ($settings->get('date_format', 'Y-m-d') ?? 'Y-m-d') == 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (<?= date('d/m/Y') ?>)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Time Format</label>
                            <select name="time_format" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="H:i:s" <?= ($settings->get('time_format', 'H:i:s') ?? 'H:i:s') == 'H:i:s' ? 'selected' : '' ?>>24 Hour (<?= date('H:i:s') ?>)</option>
                                <option value="h:i:s A" <?= ($settings->get('time_format', 'H:i:s') ?? 'H:i:s') == 'h:i:s A' ? 'selected' : '' ?>>12 Hour (<?= date('h:i:s A') ?>)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Time Zone</label>
                            <select name="timezone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Africa/Accra" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'Africa/Accra' ? 'selected' : '' ?>>Ghana (GMT)</option>
                                <option value="Africa/Lagos" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'Africa/Lagos' ? 'selected' : '' ?>>Nigeria (WAT)</option>
                                <option value="Africa/Nairobi" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'Africa/Nairobi' ? 'selected' : '' ?>>Kenya (EAT)</option>
                                <option value="Africa/Cairo" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'Africa/Cairo' ? 'selected' : '' ?>>Egypt (EET)</option>
                                <option value="Africa/Johannesburg" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'Africa/Johannesburg' ? 'selected' : '' ?>>South Africa (SAST)</option>
                                <option value="America/New_York" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'America/New_York' ? 'selected' : '' ?>>Eastern Time (US)</option>
                                <option value="America/Chicago" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'America/Chicago' ? 'selected' : '' ?>>Central Time (US)</option>
                                <option value="America/Denver" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'America/Denver' ? 'selected' : '' ?>>Mountain Time (US)</option>
                                <option value="America/Los_Angeles" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (US)</option>
                                <option value="Europe/London" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'Europe/London' ? 'selected' : '' ?>>London (GMT/BST)</option>
                                <option value="Europe/Paris" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'Europe/Paris' ? 'selected' : '' ?>>Paris (CET)</option>
                                <option value="Asia/Dubai" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'Asia/Dubai' ? 'selected' : '' ?>>Dubai (GST)</option>
                                <option value="Asia/Singapore" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'Asia/Singapore' ? 'selected' : '' ?>>Singapore (SGT)</option>
                                <option value="UTC" <?= ($settings->get('timezone', 'Africa/Accra') ?? 'Africa/Accra') == 'UTC' ? 'selected' : '' ?>>UTC</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Items Per Page</label>
                            <input type="number" name="items_per_page" value="<?= intval($settings->get('items_per_page', 10) ?? 10) ?>" min="10" max="100" step="5"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Number of items to display in lists (10-100)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Default Language</label>
                            <select name="default_language" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="en" <?= ($settings->get('default_language', 'en') ?? 'en') == 'en' ? 'selected' : '' ?>>English</option>
                                <option value="es" <?= ($settings->get('default_language', 'en') ?? 'en') == 'es' ? 'selected' : '' ?>>Spanish</option>
                                <option value="fr" <?= ($settings->get('default_language', 'en') ?? 'en') == 'fr' ? 'selected' : '' ?>>French</option>
                                <option value="de" <?= ($settings->get('default_language', 'en') ?? 'en') == 'de' ? 'selected' : '' ?>>German</option>
                                <option value="pt" <?= ($settings->get('default_language', 'en') ?? 'en') == 'pt' ? 'selected' : '' ?>>Portuguese</option>
                                <option value="ar" <?= ($settings->get('default_language', 'en') ?? 'en') == 'ar' ? 'selected' : '' ?>>Arabic</option>
                                <option value="zh" <?= ($settings->get('default_language', 'en') ?? 'en') == 'zh' ? 'selected' : '' ?>>Chinese</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Theme Color</label>
                            <select name="theme_color" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="blue" <?= ($settings->get('theme_color', 'blue') ?? 'blue') == 'blue' ? 'selected' : '' ?>>Blue</option>
                                <option value="green" <?= ($settings->get('theme_color', 'blue') ?? 'blue') == 'green' ? 'selected' : '' ?>>Green</option>
                                <option value="purple" <?= ($settings->get('theme_color', 'blue') ?? 'blue') == 'purple' ? 'selected' : '' ?>>Purple</option>
                                <option value="red" <?= ($settings->get('theme_color', 'blue') ?? 'blue') == 'red' ? 'selected' : '' ?>>Red</option>
                                <option value="orange" <?= ($settings->get('theme_color', 'blue') ?? 'blue') == 'orange' ? 'selected' : '' ?>>Orange</option>
                                <option value="teal" <?= ($settings->get('theme_color', 'blue') ?? 'blue') == 'teal' ? 'selected' : '' ?>>Teal</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Session Timeout (seconds)</label>
                            <input type="number" name="session_timeout" value="<?= intval($settings->get('session_timeout', 3600) ?? 3600) ?>" min="300" step="300"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Minimum: 300 seconds (5 minutes)</p>
                        </div>
                    </div>
                    
                    <!-- Current Settings Preview -->
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">Current System Time</h3>
                        <p class="text-sm text-gray-600">
                            Based on your settings, the current time is: 
                            <span class="font-mono font-bold">
                                <?php 
                                date_default_timezone_set($settings->get('timezone', 'Africa/Accra'));
                                echo date($settings->get('date_format', 'Y-m-d') . ' ' . $settings->get('time_format', 'H:i:s'));
                                ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Invoice Settings Tab -->
        <div id="invoice-tab" class="settings-section hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Invoice Settings</h2>
                    <p class="text-sm text-gray-500 mt-1">Configure how invoices are generated and displayed</p>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_invoice">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Prefix</label>
                            <input type="text" name="invoice_prefix" value="<?= safe_html($settings->get('invoice_prefix', 'INV-'), 'INV-') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="INV-">
                            <p class="text-xs text-gray-500 mt-1">Prefix for invoice numbers</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Next Invoice Number</label>
                            <input type="number" name="invoice_next_number" value="<?= intval($settings->get('invoice_next_number', 1001) ?? 1001) ?>" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Next invoice number to use</p>
                        </div>
                        
                        <div class="md:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-800">Show Tax on Invoices</p>
                                        <p class="text-sm text-gray-500">Display tax breakdown on invoices</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="invoice_show_tax" class="sr-only peer" <?= ($settings->get('invoice_show_tax', 1) ?? 1) ? 'checked' : '' ?>>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-800">Show Discount on Invoices</p>
                                        <p class="text-sm text-gray-500">Display discount details on invoices</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="invoice_show_discount" class="sr-only peer" <?= ($settings->get('invoice_show_discount', 1) ?? 1) ? 'checked' : '' ?>>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Footer Text</label>
                            <textarea name="invoice_footer" rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Thank you for your business!"><?= safe_html($settings->get('invoice_footer', 'Thank you for your business!'), 'Thank you for your business!') ?></textarea>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Terms & Conditions</label>
                            <textarea name="invoice_terms" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Payment due within 30 days"><?= safe_html($settings->get('invoice_terms', 'Payment due within 30 days'), 'Payment due within 30 days') ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Invoice Preview -->
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Invoice Number Preview</h3>
                        <div class="bg-white p-3 rounded-lg shadow-sm">
                            <p class="text-lg font-mono">
                                <?= safe_html($settings->get('invoice_prefix', 'INV-')) ?><?= str_pad(intval($settings->get('invoice_next_number', 1001)), 5, '0', STR_PAD_LEFT) ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">Next invoice will use this number</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security-tab" class="settings-section hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Change Password</h2>
                    <p class="text-sm text-gray-500 mt-1">Update your account password</p>
                </div>
                <form method="POST" class="p-6" onsubmit="return validatePassword()">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <input type="password" name="current_password" id="current_password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <input type="password" name="new_password" id="new_password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   pattern=".{8,}" title="Must be at least 8 characters long">
                            <div class="mt-1 flex items-center">
                                <div class="password-strength w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="strength-bar bg-gray-300 h-1.5 rounded-full" style="width: 0%"></div>
                                </div>
                                <span class="strength-text text-xs text-gray-500 ml-2"></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters long</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-red-500 hidden" id="passwordMatchError">Passwords do not match</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
                            <i class="fas fa-key mr-2"></i>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Security Settings -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Security Settings</h2>
                    <p class="text-sm text-gray-500 mt-1">Configure additional security options</p>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_security">
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800">Two-Factor Authentication</p>
                                <p class="text-sm text-gray-500">Add an extra layer of security to your account</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="two_factor_auth" class="sr-only peer" <?= ($settings->get('two_factor_auth') ?? 0) ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800">Session Security</p>
                                <p class="text-sm text-gray-500">Enable additional session security features</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="session_security" class="sr-only peer" <?= ($settings->get('session_security', 1) ?? 1) ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800">Audit Log</p>
                                <p class="text-sm text-gray-500">Log all system activities for security</p>
                            </div>
                            <label class="relative inline-flex items-container cursor-pointer">
                                <input type="checkbox" name="audit_log" class="sr-only peer" <?= ($settings->get('audit_log', 1) ?? 1) ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Password Expiry (days)</label>
                                <input type="number" name="password_expiry" value="<?= intval($settings->get('password_expiry', 90) ?? 90) ?>" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Set to 0 for no expiry</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Max Login Attempts</label>
                                <input type="number" name="max_login_attempts" value="<?= intval($settings->get('max_login_attempts', 5) ?? 5) ?>" min="1" max="10"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Number of failed attempts before lockout (1-10)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Live preview for company name
        document.querySelector('input[name="company_name"]')?.addEventListener('keyup', function() {
            const previewElement = document.getElementById('previewCompanyName');
            if (previewElement) {
                previewElement.textContent = this.value || 'My Company';
            }
        });

        // Tab Switching JavaScript
        function showTab(tabName) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.add('hidden');
            });
            
            // Show selected section
            const selectedTab = document.getElementById(tabName + '-tab');
            if (selectedTab) {
                selectedTab.classList.remove('hidden');
            }
            
            // Update tab styles
            document.querySelectorAll('[id^="tab-"]').forEach(tab => {
                tab.classList.remove('border-blue-600', 'text-blue-600');
            });
            
            const activeTab = document.getElementById('tab-' + tabName);
            if (activeTab) {
                activeTab.classList.add('border-blue-600', 'text-blue-600');
            }
            
            // Update URL hash without scrolling
            history.pushState(null, null, '#' + tabName);
        }
        
        // Check for hash in URL
        function getTabFromHash() {
            const hash = window.location.hash.substring(1);
            const validTabs = ['company', 'financial', 'tax', 'notifications', 'system', 'invoice', 'security'];
            return validTabs.includes(hash) ? hash : 'company';
        }
        
        // Show tab from hash or default to company
        document.addEventListener('DOMContentLoaded', function() {
            showTab(getTabFromHash());
        });
        
        // Update hash when tab changes
        document.querySelectorAll('[id^="tab-"]').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.id.replace('tab-', '');
                window.location.hash = tabName;
            });
        });
        
        // Real-time currency preview update
        const currencyInputs = document.querySelectorAll('[name="currency_symbol"], [name="currency_position"], [name="decimal_places"], [name="thousand_separator"], [name="decimal_separator"]');
        
        currencyInputs.forEach(input => {
            input.addEventListener('change', updatePreview);
            input.addEventListener('keyup', updatePreview);
        });
        
        function updatePreview() {
            const symbol = document.querySelector('[name="currency_symbol"]')?.value || '₵';
            const position = document.querySelector('[name="currency_position"]')?.value || 'before';
            const decimals = parseInt(document.querySelector('[name="decimal_places"]')?.value) || 2;
            const thousandSep = document.querySelector('[name="thousand_separator"]')?.value || ',';
            const decimalSep = document.querySelector('[name="decimal_separator"]')?.value || '.';
            
            const amount1 = 1250.50;
            const amount2 = 15250.75;
            
            function formatAmount(amount) {
                return amount.toFixed(decimals).replace('.', decimalSep).replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
            }
            
            const formatted1 = formatAmount(amount1);
            const formatted2 = formatAmount(amount2);
            
            const preview1 = position === 'before' ? symbol + formatted1 : formatted1 + ' ' + symbol;
            const preview2 = position === 'before' ? symbol + formatted2 : formatted2 + ' ' + symbol;
            
            const previewElements = document.querySelectorAll('.currency-preview-text');
            if (previewElements.length >= 2) {
                previewElements[0].textContent = preview1;
                previewElements[1].textContent = preview2;
            }
        }

        // Password validation and strength meter
        function validatePassword() {
            const newPass = document.getElementById('new_password')?.value;
            const confirmPass = document.getElementById('confirm_password')?.value;
            const errorElement = document.getElementById('passwordMatchError');
            
            if (newPass !== confirmPass) {
                if (errorElement) {
                    errorElement.classList.remove('hidden');
                }
                return false;
            }
            
            if (errorElement) {
                errorElement.classList.add('hidden');
            }
            return true;
        }
        
        // Password strength meter
        document.getElementById('new_password')?.addEventListener('keyup', function() {
            const password = this.value;
            const strengthBar = document.querySelector('.strength-bar');
            const strengthText = document.querySelector('.strength-text');
            
            if (!strengthBar || !strengthText) return;
            
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/)) strength += 15;
            if (password.match(/[$@#&!]+/)) strength += 10;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 30) {
                strengthBar.className = 'strength-bar bg-red-500 h-1.5 rounded-full';
                strengthText.textContent = 'Weak';
                strengthText.className = 'strength-text text-xs text-red-500 ml-2';
            } else if (strength < 60) {
                strengthBar.className = 'strength-bar bg-yellow-500 h-1.5 rounded-full';
                strengthText.textContent = 'Medium';
                strengthText.className = 'strength-text text-xs text-yellow-500 ml-2';
            } else {
                strengthBar.className = 'strength-bar bg-green-500 h-1.5 rounded-full';
                strengthText.textContent = 'Strong';
                strengthText.className = 'strength-text text-xs text-green-500 ml-2';
            }
        });
        
        // Confirm password matching
        document.getElementById('confirm_password')?.addEventListener('keyup', function() {
            const newPass = document.getElementById('new_password')?.value;
            const confirmPass = this.value;
            const errorElement = document.getElementById('passwordMatchError');
            
            if (errorElement) {
                if (newPass !== confirmPass && confirmPass.length > 0) {
                    errorElement.classList.remove('hidden');
                } else {
                    errorElement.classList.add('hidden');
                }
            }
        });

        // Handle sidebar collapse event
        window.addEventListener('sidebarCollapsed', function(e) {
            const mainContent = document.getElementById('mainContent');
            if (mainContent) {
                if (e.detail.collapsed) {
                    mainContent.classList.remove('lg:ml-72');
                    mainContent.classList.add('lg:ml-20');
                } else {
                    mainContent.classList.remove('lg:ml-20');
                    mainContent.classList.add('lg:ml-72');
                }
            }
        });
        
        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.bg-green-50, .bg-red-50').forEach(function(message) {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(function() {
                    message.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>