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
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
        ";
    } else {
        return '';
    }
}

/**
 * Small helper that returns a Bootstrap alert (text is escaped).
 * $type: success, danger, warning
 */
function alertBox(string $type, string $text): string
{
    return '<div class="alert alert-' . $type . '">' . htmlspecialchars($text) . '</div>';
}

/**
 * Prints « 1 2 3 » page buttons.
 * $base = url that already has sort/dir/search, the page number is added at the end.
 */
function renderPagination(string $base, int $page, int $totalPages): void
{
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);

    echo '<nav><ul class="pagination">';

    echo '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '">';
    echo '<a class="page-link" href="' . $base . '&page=' . ($page - 1) . '">&laquo;</a></li>';

    for ($i = $start; $i <= $end; $i++) {
        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
        echo '<a class="page-link" href="' . $base . '&page=' . $i . '">' . $i . '</a></li>';
    }

    echo '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '">';
    echo '<a class="page-link" href="' . $base . '&page=' . ($page + 1) . '">&raquo;</a></li>';

    echo '</ul></nav>';
}
?>