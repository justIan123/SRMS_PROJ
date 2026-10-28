<?php
require_once("../public/initialize.php");

require_once(DB_PATH);
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
      top: 0; 
      left: 0;
      z-index: 10;
    }

    /* HTMX adds .htmx-request while loading */
    #global-spinner.htmx-request {
      display: flex;
    }

    /* Sidebar collapse styling and animation with zero gaps */
    #pushSidebar {
      width: 260px;
      min-width: 260px;
      transition: all 0.3s ease-in-out;
      overflow: hidden;
      white-space: nowrap;
    }

    #pushSidebar.collapsed {
      width: 0 !important;
      min-width: 0 !important;
      padding: 0 !important;
      margin: 0 !important;
      border: none !important;
      opacity: 0;
    }

    /* Main wrapper styling */
    #main-wrapper {
      position: relative;
    }

    /* Main content styling with smooth opacity transition */
    #main {
      min-height: 150px; /* reserve space */
      transition: opacity 0.2s ease-in-out;
    }

    /* Show opacity / dimmed loading state instead of hiding completely */
    #main.htmx-request {
      opacity: 0.3; /* Adjust between 0.1 and 0.5 for desired fade level */
      pointer-events: none; /* Prevents clicking links/buttons while loading */
    }
  </style>
</head>
<body>
  <!-- HEADER -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-3">
    <div class="container-fluid">
      <span class="badge bg-<?= $isUserIsAdmin ? 'success' : 'secondary' ?> me-3">
        <?= $isUserIsAdmin ? 'Admin' : 'User' ?>
      </span>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
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
      </div>
    </div>
  </nav>

  <!-- NAVBAR & SIDEBAR -->
  <div class="d-flex overflow-hidden" style="min-height: 100vh">
    <nav id="pushSidebar" class="d-flex flex-column flex-shrink-0 bg-body border-end p-3" hx-indicator="#global-spinner, #main">
      <a href="#" class="mb-3 link-body-emphasis text-decoration-none fs-5 fw-semibold"><i class="bi bi-hexagon-half me-2"></i>SRMS</a>
      
      <!-- LINK LISTS -->
       <?php if($isUserIsAdmin):?>
      <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
          <div class="nav-link btn text-start active"
            hx-get="<?= COMPONENTS_URL . 'dashboard_content.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
              <i class="bi bi-speedometer2 me-2"></i>Dashboard
          </div>
        </li>

        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_students.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-people me-2"></i>Add Students
          </div>
        </li>

        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_users.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-person-add me-2"></i>Manage User's & Teachers
          </div>
        </li>

        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_subjects.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-book"></i>Manage Subjects
          </div>
        </li>

        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_guardians.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-person-add me-2"></i>Add Guardians
          </div>
        </li>

        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_schoolYear.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-calendar"></i>School Year
          </div>
        </li>

        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_myAccount.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-person"></i>My Account
          </div>
        </li>
        <?php endif?>

        <?php if(!$isUserIsAdmin):?>
        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_questions.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-person"></i>My questions
          </div>
        </li>
        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_test.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-person"></i>My test
          </div>
        </li>
        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_tests.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-person"></i>My tests
          </div>
        </li>
        <?php endif?>

        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-graph-up me-2"></i>Reports</a></li>
        <li class="nav-item"><a class="nav-link" href="logout_page"><i class="bi bi-door-closed me-2"></i>Logout</a></li>
      </ul>
    </nav>

    <header class="container-fluid flex-grow-1 p-3" hx-indicator="#global-spinner, #main">
      <div class="row mb-3">
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
        <div id="main">
          <!-- Initial auto-load component -->
          <div class="nav-link btn text-start active"
            hx-get="<?= COMPONENTS_URL . 'dashboard_content.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            hx-trigger="load">
          </div>
        </div>
      </div>
    </header>
  </div>

  <script src="https://unpkg.com/htmx.org@1.9.10"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    function setActive(el) {
      document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
      });
      el.classList.add('active');
    }
  </script>
</body>
</html>