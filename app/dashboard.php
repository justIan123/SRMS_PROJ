<?php
require_once("../public/index.php");

require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(INCLUDE_PATH . DS . "auth.php");


?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SRMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- bootstrap icons CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

  <style>
    /* Hide spinner initially */
    #global-spinner {
      display: none;
      top:0; left:0;
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
<body>
  <!-- HEADER -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light">

    <div class="navbar-brand">
      <!-- <h3><?= htmlspecialchars($_SESSION['user']) ?>!</h3> -->
    </div>


    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse border-bottom" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <li class="nav-item">
          <a class="nav-link active" aria-current="page" href="#">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Features</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Pages
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
            <li><a class="dropdown-item" href="#">Page 1</a></li>
            <li><a class="dropdown-item" href="#">Page 2</a></li>
          </ul>
        </li>
      </ul>

      <form class="d-flex">
        <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
        <button class="btn btn-outline-success" type="submit">Search</button>
      </form>

    </div>

  </nav>

  <!-- NAVBAR -->
  <!-- https://adminlte.io/blog/bootstrap-sidebars/#collapsetoggle -->

  <style>
    #pushSidebar { width: 260px; transition: margin-left .25s ease; }
    #pushSidebar.collapsed { margin-left: -260px; }
  </style>

  <div class="d-flex overflow-hidden" style="min-height: 100vh">
    <nav id="pushSidebar" class="d-flex flex-column flex-shrink-0 bg-body border-end p-3">
      <a href="#" class="mb-3 link-body-emphasis text-decoration-none fs-5 fw-semibold"><i class="bi bi-hexagon-half me-2"></i>SRMS</a>
      <!-- LINK LISTS -->
      <ul class="nav nav-pills flex-column mb-auto">

        <li class="nav-item">
          <div class="nav-link btn text-start active"
            hx-get="<?= COMPONENTS_URL . 'dashboard_content.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            hx-indicator="#global-spinner"
            onclick="setActive(this)"
            >
            <!-- hx-trigger="load" means to load this page automatically -->
              <i class="bi bi-speedometer2 me-2"></i>
              Dashboard
          </div>
        </li>


        <li class="nav-item">
          <!-- i hate this onclick -->
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_users.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            hx-indicator="#global-spinner"
            onclick="setActive(this)"
            >
            <i class="bi bi-person-add me-2"></i>
              Add Teachers
          </div>
        </li>

        <li class="nav-item">

          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_guardians.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            hx-indicator="#global-spinner"
            onclick="setActive(this)"
            >
            <i class="bi bi-person-add me-2"></i>
              Add Guardians
          </div>
        </li>

        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-box-seam me-2"></i>Products</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-people me-2"></i>Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-graph-up me-2"></i>Reports</a></li>
        <li class="nav-item"><a class="nav-link" href="logout_page"><i class="bi bi-door-closed me-2"></i>Logout</a></li>
      </ul>
    </nav>
    <header class="container-fluid">

      <div class="row">
        <!-- close navbar -->
        <div class="col-6 d-flex justify-content-start">
          <button class="btn btn-outline-secondary" onclick="document.getElementById('pushSidebar').classList.toggle('collapsed')" aria-label="Toggle sidebar" aria-expanded="true">
              <i class="bi bi-list"></i>
          </button>
        </div>
        <div class="col-6 d-flex justify-content-end">
          <div id="global-spinner">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
        </div>
      </div>

      
      <!-- MAIN -->
      
      <div id="main-wrapper">
        <!-- Spinner overlay -->

        <!-- nontent area -->
        <div id="main">
          <!-- page loads this automatically loads so, when page loads no empty dashboard -->
          <div class="nav-link btn text-start active"
            hx-get="<?= COMPONENTS_URL . 'dashboard_content.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            hx-indicator="#global-spinner"
            hx-trigger="load"
            >
            <!-- hx-trigger="load" means to load this page automatically -->
          </div>
        </div>
      </div>

    </header>
  </div>

  

  <!-- Wrapper -->

<!-- <div id="main-wrapper" style="position:relative;"> -->

    <!-- spinner overlay -->
    <!-- <div id="global-spinner">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div> -->

    <!-- content area -->
    <!-- <div id="main" class="mt-3 border p-3">
      <h4>Dashboard Content</h4>
      <p>Click a button to load content.</p>
    </div>
  </div>  -->

  <!-- Example navigation -->
  <!-- <button class="btn btn-primary me-2"
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
  </button> -->

  <script src="https://unpkg.com/htmx.org@1.9.10"></script>
</body>
</html>

<!-- RUKLE -->
<!--
When using htmx
when get a request and the target is the other div use hx-indicator="#global-spinner
for load the page dynamically

hx-indicator is an HTMX attribute that lets you specify which element(s) should receive the htmx-request
CSS class during an HTMX request, enabling spinners or loading indicators to appear only while the request is loading
-->


<script>
// purpose to set highlight to a list when active, i hate ts
function setActive(el) {
  document.querySelectorAll('.nav-link').forEach(link => {
    link.classList.remove('active');
  });

  el.classList.add('active');
}

</script>