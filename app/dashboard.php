<?php
require_once("../public/index.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(INCLUDE_PATH . DS . "auth.php");
?>
<h2>Welcome, <?= htmlspecialchars($_SESSION['user']) ?>!</h2>

<a href="logout_page.php">Logout</a>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HTMX Spinner Overlay</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* Hide spinner initially */
    #global-spinner {
      display: none;
      position: absolute;
      top:0; left:0; width:100%; height:100%;
      background: rgb(255, 255, 255);
      justify-content: center;
      align-items: center;
      z-index: 10;
    }

    /* HTMX adds .htmx-request while loading */
    #global-spinner.htmx-request {
      display: flex;
    }

    /* Hide main content while loading */
    #main.htmx-request {
      display: none;
    }

    #main {
      min-height: 150px; /* reserve space */
    }
  </style>
</head>
<body class="p-3">

  <!-- Wrapper -->
  <div id="main-wrapper" style="position:relative;">

    <!-- Spinner overlay -->
    <div id="global-spinner">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <!-- Content area -->
    <div id="main" class="mt-3 border p-3">
      <h4>Dashboard Content</h4>
      <p>Click a button to load content.</p>
    </div>
  </div>

  <!-- Example navigation -->
  <button class="btn btn-primary me-2"
          hx-get="main_users.php"
          hx-target="#main"
          hx-swap="innerHTML"
          hx-indicator="#global-spinner">
    Load Users
  </button>

  <button class="btn btn-success"
          hx-get="main_settings.php"
          hx-target="#main"
          hx-swap="innerHTML"
          hx-indicator="#global-spinner">
    Load Reports
  </button>

  <script src="https://unpkg.com/htmx.org@1.9.10"></script>
</body>
</html>
