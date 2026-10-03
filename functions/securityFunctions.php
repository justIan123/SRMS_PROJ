<?php
/**
 * Sanitize user input based on type
 *
 * @param mixed  $data The input value to sanitize
 * @param string $type The type of data: string, email, url, int, float
 * @return mixed Sanitized value
 * <p>Use case: $ getSanitizeVar1 = sanitize(_POST['htmlInput'], 'string') </p>
 * <p>Use case: $ getSanitizeVar2= sanitize(_POST['username'], 'string') </p>
 * <p>Use case: $ getSanitizeVar3 = sanitize(_POST['email'], 'email') </p>
 */
function sanitize($data, string $type = 'string') {
    if (!isset($data)) {
        return null; // Handle unset variables safely
    }

    switch (strtolower($type)) {
        case 'string':
            // Remove HTML tags and encode special characters
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');

        case 'email':
            return filter_var(trim($data), FILTER_SANITIZE_EMAIL);

        case 'url':
            return filter_var(trim($data), FILTER_SANITIZE_URL);

        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);

        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        default:
            // Fallback to safe string
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

?>