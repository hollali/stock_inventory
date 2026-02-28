<?php
// config/Settings.php

class Settings {
    private $conn;
    private $cache = [];
    private $table = 'settings';
    
    public function __construct($db) {
        $this->conn = $db;
        $this->loadAllSettings();
    }
    
    /**
     * Load all settings into cache
     */
    private function loadAllSettings() {
        $query = "SELECT setting_key, setting_value, setting_type FROM {$this->table}";
        $result = mysqli_query($this->conn, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $this->cache[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type']);
            }
        }
    }
    
    /**
     * Cast value based on type
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'boolean':
                return in_array($value, ['1', 1, 'true', true], true);
            case 'json':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }
    
    /**
     * Get a setting value
     */
    public function get($key, $default = null) {
        return $this->cache[$key] ?? $default;
    }
    
    /**
     * Set a setting value
     */
    public function set($key, $value, $type = 'text') {
        $escaped_value = mysqli_real_escape_string($this->conn, $value);
        
        $query = "INSERT INTO {$this->table} (setting_key, setting_value, setting_type) 
                  VALUES ('$key', '$escaped_value', '$type')
                  ON DUPLICATE KEY UPDATE setting_value = '$escaped_value', setting_type = '$type'";
        
        if (mysqli_query($this->conn, $query)) {
            $this->cache[$key] = $this->castValue($value, $type);
            return true;
        }
        return false;
    }
    
    /**
     * Update multiple settings at once
     */
    public function updateMany($settings, $category = null) {
        $success = true;
        
        foreach ($settings as $key => $value) {
            $type = $this->detectType($value);
            if (!$this->set($key, $value, $type)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Detect value type
     */
    private function detectType($value) {
        if (is_numeric($value)) {
            return 'number';
        } elseif (is_bool($value) || in_array($value, ['0', '1', 0, 1], true)) {
            return 'boolean';
        } elseif (is_array($value)) {
            return 'json';
        } else {
            return 'text';
        }
    }
    
    /**
     * Get all settings by category
     */
    public function getByCategory($category) {
        $query = "SELECT setting_key, setting_value, setting_type, description 
                  FROM {$this->table} 
                  WHERE category = '$category' 
                  ORDER BY setting_key";
        
        $result = mysqli_query($this->conn, $query);
        $settings = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $settings[$row['setting_key']] = [
                'value' => $this->castValue($row['setting_value'], $row['setting_type']),
                'type' => $row['setting_type'],
                'description' => $row['description']
            ];
        }
        
        return $settings;
    }
    
    /**
     * Get all categories
     */
    public function getCategories() {
        $query = "SELECT DISTINCT category FROM {$this->table} ORDER BY category";
        $result = mysqli_query($this->conn, $query);
        $categories = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row['category'];
        }
        
        return $categories;
    }
    
    /**
     * Clear cache and reload
     */
    public function refresh() {
        $this->cache = [];
        $this->loadAllSettings();
    }
    
    /**
     * Format currency based on settings
     */
    public function formatCurrency($amount) {
        $symbol = $this->get('currency_symbol', '$');
        $decimal_places = (int)$this->get('decimal_places', 2);
        return $symbol . number_format($amount, $decimal_places);
    }
    
    /**
     * Format date based on settings
     */
    public function formatDate($date) {
        $format = $this->get('date_format', 'Y-m-d');
        return date($format, strtotime($date));
    }
    
    /**
     * Format datetime based on settings
     */
    public function formatDateTime($datetime) {
        $date_format = $this->get('date_format', 'Y-m-d');
        $time_format = $this->get('time_format', 'H:i:s');
        return date($date_format . ' ' . $time_format, strtotime($datetime));
    }
    
    /**
     * Check if low stock alert should be triggered
     */
    public function isLowStock($current_quantity) {
        if (!$this->get('low_stock_alert', true)) {
            return false;
        }
        $threshold = $this->get('low_stock_threshold', 10);
        return $current_quantity <= $threshold;
    }
}