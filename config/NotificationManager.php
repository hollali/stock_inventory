<?php
// config/NotificationManager.php

class NotificationManager {
    private $conn;
    private $settings;
    
    public function __construct($db, $settings) {
        $this->conn = $db;
        $this->settings = $settings;
    }
    
    /**
     * Check for low stock and send alerts
     */
    public function checkLowStock() {
        if (!$this->settings->get('low_stock_alert', true)) {
            return false;
        }
        
        $threshold = $this->settings->get('low_stock_threshold', 10);
        
        $query = "SELECT p.*, 
                         COALESCE(c.name, 'Uncategorized') as category_name
                  FROM products p
                  LEFT JOIN product_categories pc ON p.id = pc.product_id
                  LEFT JOIN categories c ON pc.category_id = c.id
                  WHERE p.quantity <= $threshold
                  ORDER BY p.quantity ASC";
        
        $result = mysqli_query($this->conn, $query);
        $low_stock_products = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $low_stock_products[] = $row;
        }
        
        if (!empty($low_stock_products)) {
            $this->sendLowStockAlert($low_stock_products);
        }
        
        return $low_stock_products;
    }
    
    /**
     * Send low stock alert email
     */
    private function sendLowStockAlert($products) {
        if (!$this->settings->get('email_notifications', true)) {
            return false;
        }
        
        $to = $this->settings->get('notification_email');
        $subject = "Low Stock Alert - " . $this->settings->get('company_name');
        
        $message = "<h2>Low Stock Products</h2>";
        $message .= "<p>The following products are running low:</p>";
        $message .= "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        $message .= "<tr><th>Product</th><th>SKU</th><th>Current Stock</th><th>Category</th></tr>";
        
        foreach ($products as $product) {
            $message .= "<tr>";
            $message .= "<td>" . htmlspecialchars($product['name']) . "</td>";
            $message .= "<td>" . htmlspecialchars($product['sku']) . "</td>";
            $message .= "<td style='color: red; font-weight: bold;'>" . $product['quantity'] . "</td>";
            $message .= "<td>" . htmlspecialchars($product['category_name']) . "</td>";
            $message .= "</tr>";
        }
        
        $message .= "</table>";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $this->settings->get('company_email') . "\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send daily summary email
     */
    public function sendDailySummary() {
        if (!$this->settings->get('daily_summary', false)) {
            return false;
        }
        
        $to = $this->settings->get('notification_email');
        $subject = "Daily Summary - " . $this->settings->get('company_name');
        
        // Get summary data
        $summary = $this->getDailySummary();
        
        $message = "<h2>Daily Inventory Summary - " . date('Y-m-d') . "</h2>";
        
        $message .= "<h3>Overview</h3>";
        $message .= "<ul>";
        $message .= "<li>Total Products: " . $summary['total_products'] . "</li>";
        $message .= "<li>Total Stock: " . $summary['total_stock'] . " units</li>";
        $message .= "<li>Total Value: " . $this->settings->formatCurrency($summary['total_value']) . "</li>";
        $message .= "<li>Low Stock Items: " . $summary['low_stock_count'] . "</li>";
        $message .= "</ul>";
        
        $message .= "<h3>Today's Activity</h3>";
        $message .= "<ul>";
        $message .= "<li>Products Added: " . $summary['added_today'] . "</li>";
        $message .= "<li>Stock In: " . $summary['stock_in_today'] . " units</li>";
        $message .= "<li>Stock Out: " . $summary['stock_out_today'] . " units</li>";
        $message .= "<li>Revenue Today: " . $this->settings->formatCurrency($summary['revenue_today']) . "</li>";
        $message .= "</ul>";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $this->settings->get('company_email') . "\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    /**
     * Get daily summary data
     */
    private function getDailySummary() {
        $today = date('Y-m-d');
        
        $summary = [];
        
        // Total products
        $result = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM products");
        $summary['total_products'] = mysqli_fetch_assoc($result)['count'];
        
        // Total stock
        $result = mysqli_query($this->conn, "SELECT SUM(quantity) as total FROM products");
        $summary['total_stock'] = mysqli_fetch_assoc($result)['total'] ?? 0;
        
        // Total value
        $result = mysqli_query($this->conn, "SELECT SUM(price * quantity) as total FROM products");
        $summary['total_value'] = mysqli_fetch_assoc($result)['total'] ?? 0;
        
        // Low stock count
        $threshold = $this->settings->get('low_stock_threshold', 10);
        $result = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM products WHERE quantity < $threshold");
        $summary['low_stock_count'] = mysqli_fetch_assoc($result)['count'];
        
        // Today's additions
        $result = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM products WHERE DATE(created_at) = '$today'");
        $summary['added_today'] = mysqli_fetch_assoc($result)['count'];
        
        // Today's stock movements
        $result = mysqli_query($this->conn, "
            SELECT 
                SUM(CASE WHEN change_type = 'IN' THEN quantity ELSE 0 END) as stock_in,
                SUM(CASE WHEN change_type = 'OUT' THEN quantity ELSE 0 END) as stock_out
            FROM stock_logs 
            WHERE DATE(created_at) = '$today'
        ");
        $movement = mysqli_fetch_assoc($result);
        $summary['stock_in_today'] = $movement['stock_in'] ?? 0;
        $summary['stock_out_today'] = $movement['stock_out'] ?? 0;
        
        // Today's revenue (from OUT movements)
        $result = mysqli_query($this->conn, "
            SELECT SUM(sl.quantity * p.price) as revenue
            FROM stock_logs sl
            JOIN products p ON sl.product_id = p.id
            WHERE sl.change_type = 'OUT' AND DATE(sl.created_at) = '$today'
        ");
        $summary['revenue_today'] = mysqli_fetch_assoc($result)['revenue'] ?? 0;
        
        return $summary;
    }
}