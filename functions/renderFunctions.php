<?php
/**
 * @param string $message enter msg that show JS alert message
 * use for debug 
 */
function alert(String $message) {
    $safeMessage = json_encode($message);
    
    ;echo "<script type='text/javascript'>alert($safeMessage);</script>";
}

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
function renderPagination(string $base, int $page, string $pageNameNum, int $totalPages): void
{
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);

    echo '<nav><ul class="pagination">';

    echo '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '">';
    echo '<a class="page-link" href="' . $base . '&' . $pageNameNum . '=' . ($totalPages - $totalPages) . '"><strong>&laquo;&laquo;</strong></a></li>';
    echo '<a class="page-link" href="' . $base . '&' . $pageNameNum . '=' . ($page - 1) . '">&laquo;</a></li>';

    for ($i = $start; $i <= $end; $i++) {
        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
        echo '<a class="page-link" href="' . $base . '&' . $pageNameNum . '=' . $i . '">' . $i . '</a></li>';
    }

    echo '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '">';
    echo '<a class="page-link" href="' . $base . '&' . $pageNameNum . '=' . ($page + 1) . '">&raquo;</a></li>';
    echo '<a class="page-link" href="' . $base . '&' . $pageNameNum . '=' . ($totalPages) . '"><strong>&raquo;&raquo;</strong></a></li>';

    echo '</ul></nav>';
}


// Makes a link to this page. It keeps the current state (search, page, ...)
// and lets you change a few things. A value of null or '' removes it from the link.
function makeUrl($self, $state, $changes = []) {
    $all   = array_merge($state, $changes);
    $clean = [];

    foreach ($all as $key => $value) {
        if ($value === null || $value === '' || $value === 0) {
            continue;
        }
        $clean[$key] = $value;
    }

    return $self . '?' . http_build_query($clean);
}


// Prints hidden inputs for the current state, so a form remembers where we are.
// $skip = names we do NOT want to print (because the form has its own input for them).
function hiddenFields($state, $skip = []) {
    foreach ($state as $key => $value) {
        if (in_array($key, $skip)) {
            continue;
        }
        echo '<input type="hidden" name="' . $key . '" value="' . htmlspecialchars((string)$value) . '">';
    }
}


// Makes the hx-vals text for buttons that post (Delete, Assign).
function postValues($state, $extra) {
    return htmlspecialchars(json_encode(array_merge($state, $extra)), ENT_QUOTES);
}
