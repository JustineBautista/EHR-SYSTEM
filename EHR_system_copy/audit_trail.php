<?php
/**
 * Audit Trail Helper Functions
 * This file contains functions to log changes to the EHR database
 */

/**
 * Log an action to the audit trail
 * 
 * @param string $action_type The type of action (INSERT, UPDATE, DELETE)
 * @param string $table_name The name of the table being modified
 * @param int $record_id The ID of the record being modified
 * @param int $patient_id The ID of the patient related to this record (if applicable)
 * @param string|array $old_values The old values (for UPDATE/DELETE)
 * @param string|array $new_values The new values (for INSERT/UPDATE)
 * @return bool Whether the audit was successfully logged
 */
function log_audit($conn, $action_type, $table_name, $record_id, $patient_id = null, $old_values = null, $new_values = null) {
    // Get current user
    $username = $_SESSION['admin'] ?? 'system';
    $user_id = $_SESSION['admin_id'] ?? 0;
    
    // Get client IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Convert arrays to JSON for storage
    if (is_array($old_values)) {
        $old_values = json_encode($old_values);
    }
    if (is_array($new_values)) {
        $new_values = json_encode($new_values);
    }
    
    // Prepare and execute the insert
    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, action_type, table_name, record_id, patient_id, old_values, new_values, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssiisss", $user_id, $username, $action_type, $table_name, $record_id, $patient_id, $old_values, $new_values, $ip_address);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get the current values of a record before updating/deleting
 * 
 * @param mysqli $conn Database connection
 * @param string $table The table name
 * @param int $id The record ID
 * @return array|null The record values or null if not found
 */
function get_record_values($conn, $table, $id) {
    $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data;
}

/**
 * View audit trail for a specific patient
 * 
 * @param mysqli $conn Database connection
 * @param int $patient_id The patient ID
 * @param int $limit Maximum number of records to return
 * @return array Audit trail entries
 */
function get_patient_audit_trail($conn, $patient_id, $limit = 100) {
    $stmt = $conn->prepare("SELECT * FROM audit_trail WHERE patient_id = ? ORDER BY action_date DESC LIMIT ?");
    $stmt->bind_param("ii", $patient_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $audit_entries = [];
    while ($row = $result->fetch_assoc()) {
        $audit_entries[] = $row;
    }
    $stmt->close();
    
    return $audit_entries;
}
?>