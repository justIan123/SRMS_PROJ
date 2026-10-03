<?php
$username = trim($_POST['username'] ?? '');

if (!empty($username)) {
    // 1. Primary response (swapped into #main-status)
    echo "<p style='color: green;'>Success: Saved $username!</p>";

    // 2. Out-of-band element for #counter
    echo "<div id='counter' hx-swap-oob='true'>Total Submissions: 1</div>";

    // 3. Out-of-band element for #activity-log
    echo "<div id='activity-log' hx-swap-oob='true'>Recent Activity: Added $username</div>";
} else {
    header("HX-Redirect: err.html");
    echo "<p id='err-status' style='color: red;'>Failed: Invalid input.</p>";
    exit;

}
?>