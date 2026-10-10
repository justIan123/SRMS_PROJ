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
        
        case 'phone':
            // Step 1: Remove all characters except digits and plus sign
            $sanitized = preg_replace('/[^0-9+]/', '', $data);

            // only one leading plus sign (international format)
            if (substr_count($sanitized, '+') > 1 || (strpos($sanitized, '+') > 0)) {
                return null; // Invalid format
            }

            // plus for validation length check
            $digitsOnly = ltrim($sanitized, '+');

            // length (common range: 7–15 digits for phone numbers)
            if (strlen($digitsOnly) < 7 || strlen($digitsOnly) > 15) {
                return null; // Invalid length
            }
            return $sanitized;
        default:
            // Fallback to safe string
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Sanitize and validate a phone number.
 *
 * @param string $phone Raw phone number input.
 * @return string|null Sanitized phone number or null if invalid.
 */
function sanitizePhoneNumber(string $phone): ?string {
    // Step 1: Remove all characters except digits and plus sign
    $sanitized = preg_replace('/[^0-9+]/', '', $phone);

    // Step 2: Ensure only one leading plus sign (international format)
    if (substr_count($sanitized, '+') > 1 || (strpos($sanitized, '+') > 0)) {
        return null; // Invalid format
    }

    // Step 3: Remove leading plus for validation length check
    $digitsOnly = ltrim($sanitized, '+');

    // Step 4: Validate length (common range: 7–15 digits for phone numbers)
    if (strlen($digitsOnly) < 7 || strlen($digitsOnly) > 15) {
        return null; // Invalid length
    }

    // Step 5: Return sanitized number in standard format
    return $sanitized;
}

?>