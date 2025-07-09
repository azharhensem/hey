<?php
/**
 * Address Validation and Formatting Utility
 * Ensures consistent address format across the system
 */

class AddressValidator {
    
    /**
     * Validate address format
     * Expected format: [Street Number] [Street Name], [Area/District], [City], [Postal Code]
     */
    public static function validateFormat($address) {
        if (empty(trim($address))) {
            return ['valid' => false, 'message' => 'Address cannot be empty'];
        }
        
        // Split by commas
        $parts = array_map('trim', explode(',', $address));
        
        if (count($parts) < 3) {
            return ['valid' => false, 'message' => 'Address must contain at least street, area, and city separated by commas'];
        }
        
        // Check for postal code (5 digits)
        if (!preg_match('/\b\d{5}\b/', $address)) {
            return ['valid' => false, 'message' => 'Address must contain a 5-digit postal code'];
        }
        
        // Check minimum length for each part
        foreach ($parts as $part) {
            if (strlen(trim($part)) < 2) {
                return ['valid' => false, 'message' => 'Each address part must be at least 2 characters long'];
            }
        }
        
        return ['valid' => true, 'message' => 'Address format is valid'];
    }
    
    /**
     * Format address consistently
     */
    public static function formatAddress($address) {
        $parts = array_map('trim', explode(',', $address));
        return implode(', ', array_filter($parts));
    }
    
    /**
     * Extract postal code from address
     */
    public static function extractPostalCode($address) {
        preg_match('/\b(\d{5})\b/', $address, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    /**
     * Get address example
     */
    public static function getExample() {
        return "123 Jalan Merdeka, Taman Sejahtera, Kuala Lumpur, 50000";
    }
}

/**
 * Address Tracking Functions
 */

function logAddressUsage($pdo, $order_id, $address_type, $address) {
    try {
        $stmt = $pdo->prepare("INSERT INTO address_log (order_id, address_type, address_used, logged_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$order_id, $address_type, $address]);
    } catch (Exception $e) {
        error_log("Address logging failed: " . $e->getMessage());
    }
}

function getAddressConsistencyReport($pdo, $order_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM address_log WHERE order_id = ? ORDER BY logged_at");
        $stmt->execute([$order_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Address consistency check failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Address Synchronization Functions
 */

function syncAddressToAllSystems($pdo, $order_id, $delivery_address) {
    try {
        // Update order record
        $stmt = $pdo->prepare("UPDATE orders SET delivery_address = ? WHERE order_id = ?");
        $stmt->execute([$delivery_address, $order_id]);
        
        // Log the address usage
        logAddressUsage($pdo, $order_id, 'checkout', $delivery_address);
        
        return true;
    } catch (Exception $e) {
        error_log("Address sync failed: " . $e->getMessage());
        return false;
    }
}

function validateAndSyncAddress($pdo, $order_id, $delivery_address) {
    $validation = AddressValidator::validateFormat($delivery_address);
    
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }
    
    $formatted_address = AddressValidator::formatAddress($delivery_address);
    $sync_result = syncAddressToAllSystems($pdo, $order_id, $formatted_address);
    
    if ($sync_result) {
        return ['success' => true, 'message' => 'Address validated and synchronized', 'address' => $formatted_address];
    } else {
        return ['success' => false, 'message' => 'Failed to synchronize address'];
    }
}
?>