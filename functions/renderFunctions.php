<?php
/**
 * @param string $message - Any alert messages you want to send to end user
 * @param string $typeAlert - bootstrap alert type
 * Values: primary, secondary, success, danger, warning, info, light, dark
 */
function renderAlert(string $message, string $typeAlert) {
    if (!empty($message)) {
        return "
            <div class='alert alert-$typeAlert alert-dismissible fade show' role='alert' id='renderAlert'>
                <strong>Warning!</strong> $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
        ";
    } else {
        return '';
    }
}

?>