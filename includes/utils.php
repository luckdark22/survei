<?php
// includes/utils.php

/**
 * Mask a numeric ID into a string
 */
function maskId($id) {
    if (!$id) return '';
    $key = getenv('APP_KEY') ?: 'secret_salt';
    
    // Simple reversible obfuscation
    // Using base64 with a simple shift
    $data = ($id * 12345) + 6789;
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data . '|' . substr($key, 0, 8)));
}

/**
 * Unmask a string back to a numeric ID
 */
function unmaskId($mask) {
    if (!$mask) return null;
    $key = getenv('APP_KEY') ?: 'secret_salt';
    
    $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $mask));
    if (!$decoded) return null;
    
    $parts = explode('|', $decoded);
    if (count($parts) !== 2) return null;
    
    // Verify salt match (simple check)
    if ($parts[1] !== substr($key, 0, 8)) return null;
    
    $id = ((int)$parts[0] - 6789) / 12345;
    return is_numeric($id) && $id > 0 ? (int)$id : null;
}
?>
