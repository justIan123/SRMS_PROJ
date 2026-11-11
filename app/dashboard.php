<!-- https://github.com/justIan123/bootstrap-dashboard-template -->
<?php
require_once("../public/initialize.php");

require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(INCLUDE_PATH . DS . "auth.php");

?>

<!-- https://github.com/justIan123/bootstrap-dashboard-template -->
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ffffff">
  <title>SRMS</title>

  <!-- Apply the saved (or system) theme before any CSS paints, so there's no light flash in dark mode -->
  <script>
    (function () {
      var theme = null;
      try { theme = localStorage.getItem('srms-theme'); } catch (e) {}
      if (theme !== 'light' && theme !== 'dark') {
        theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      }
      document.documentElement.setAttribute('data-bs-theme', theme);
      var meta = document.querySelector('meta[name="theme-color"]');
      if (meta) meta.setAttribute('content', theme === 'dark' ? '#212529' : '#ffffff');
    })();
  </script>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- bootstrap icons CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

    /* ---------- App shell ---------- */
    .app-shell {
      min-height: 100vh;
      min-height: 100dvh; /* mobile browsers: ignore the collapsing address bar */
    }

    /* Lets the content column shrink so wide content can't push the layout sideways */
    .app-content {
      min-width: 0;
    }

    /* Keep sidebar icons evenly spaced */
    #pushSidebar .nav-link > .bi {
      margin-right: 0.5rem;
    }

    /* Bigger tap targets on touch screens (Android, iPad) */
    @media (pointer: coarse) {
      #pushSidebar .nav-link {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
      }
    }

    /* ---------- Desktop + iPad landscape (>= 992px): push sidebar ---------- */
    @media (min-width: 992px) {
      .app-shell {
        overflow: hidden;
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

      #sidebarBackdrop {
        display: none;
      }
    }

    /* ---------- Phones + iPad portrait (< 992px): slide-out drawer ---------- */
    @media (max-width: 991.98px) {
      #pushSidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        width: min(280px, 85vw);
        z-index: 1045;
        overflow-y: auto;
        overscroll-behavior: contain;
        white-space: normal;
        transform: translateX(-100%);
        visibility: hidden;
        transition: transform 0.3s ease-in-out, visibility 0s linear 0.3s;
      }

      #pushSidebar.open {
        transform: translateX(0);
        visibility: visible;
        box-shadow: 0 0 2rem rgba(0, 0, 0, 0.35);
        transition: transform 0.3s ease-in-out, visibility 0s;
      }

      #sidebarBackdrop {
        position: fixed;
        inset: 0;
        z-index: 1040;
        background: rgba(0, 0, 0, 0.5);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease-in-out, visibility 0s linear 0.3s;
      }

      #sidebarBackdrop.show {
        opacity: 1;
        visibility: visible;
        transition: opacity 0.3s ease-in-out, visibility 0s;
      }
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
  <nav class="navbar navbar-expand-lg bg-body border-bottom px-3">
    <div class="container-fluid">
      <span class="badge bg-<?= $isUserIsAdmin ? 'success' : 'secondary' ?> me-3">
        <?= $isUserIsAdmin ? 'Admin' : 'User' ?>
      </span>

      <!-- Dark mode toggle (right side on every screen size) -->
      <button id="themeToggle" class="btn btn-outline-secondary btn-sm ms-auto ms-lg-0 me-2 me-lg-0 order-lg-last" type="button" aria-label="Switch to dark mode" title="Switch to dark mode">
        <i id="themeIcon" class="bi bi-moon-stars-fill"></i>
      </button>

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
  <div class="app-shell d-flex">
    <!-- Dim overlay behind the drawer on phones / iPad portrait -->
    <div id="sidebarBackdrop"></div>

    <nav id="pushSidebar" class="d-flex flex-column flex-shrink-0 bg-body border-end p-3" hx-indicator="#global-spinner, #main" aria-label="Main navigation">
      <a href="#" class="mb-3 link-body-emphasis text-decoration-none fs-5 fw-semibold"><i class="bi bi-hexagon-half me-2"></i>SRMS</a>
      
      <!-- LINK LISTS -->
      <ul class="nav nav-pills flex-column mb-auto">
        
       <?php if($isUserIsAdmin):?>
        <li class="nav-item">
          <div class="nav-link btn text-start active"
            hx-get="<?= COMPONENTS_URL . 'admin_dashboard_content.php' ?>"
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
            hx-get="<?= COMPONENTS_URL . 'manage_classRecord.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-database"></i>Class Record
          </div>
        </li>
        
        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_grades.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-pencil-fill"></i>Grades
          </div>
        </li>

        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_enrollments.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-newspaper"></i>Enrollments
          </div>
        </li>
        
        <li class="nav-item">
          <div class="nav-link btn text-start"
            hx-get="<?= COMPONENTS_URL . 'manage_recommendations.php' ?>"
            hx-target="#main"
            hx-swap="innerHTML"
            onclick="setActive(this)">
            <i class="bi bi-newspaper"></i>Student recommendations
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
              hx-get="<?= COMPONENTS_URL . 'teacher_dashboard_content.php' ?>"
              hx-target="#main"
              hx-swap="innerHTML"
              onclick="setActive(this)">
              <i class="bi bi-speedometer2"></i>Dashboard
            </div>
          </li>
          <li class="nav-item">
            <div class="nav-link btn text-start"
              hx-get="<?= COMPONENTS_URL . 'manage_grades.php' ?>"
              hx-target="#main"
              hx-swap="innerHTML"
              onclick="setActive(this)">
              <i class="bi bi-person-vcard-fill"></i>Grades
            </div>
          </li>
          <li class="nav-item">
            <div class="nav-link btn text-start"
              hx-get="<?= COMPONENTS_URL . 'manage_myStudents.php' ?>"
              hx-target="#main"
              hx-swap="innerHTML"
              onclick="setActive(this)">
              <i class="bi bi-database"></i>My Students
            </div>
          </li>
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
              hx-get="<?= COMPONENTS_URL . 'manage_tests.php' ?>"
              hx-target="#main"
              hx-swap="innerHTML"
              onclick="setActive(this)">
              <i class="bi bi-book-half"></i>Test Maker
            </div>
          </li>
        <?php endif?>

        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-graph-up me-2"></i>Reports</a></li>
        <a class="nav-link" id="logoutLink"
          href="<?= APP_URL . 'logout_page.php' ?>">
            <i class="bi bi-door-closed me-2"></i>Log-out
        </a>
        
      </ul>
    </nav>

    <header class="app-content container-fluid flex-grow-1 p-2 p-sm-3" hx-indicator="#global-spinner, #main">
      <div class="row mb-3">
        <div class="col-6 d-flex justify-content-start">
          <button id="sidebarToggle" class="btn btn-outline-secondary" type="button" onclick="toggleSidebar()" aria-label="Toggle sidebar" aria-controls="pushSidebar" aria-expanded="true">
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
           <?php if($isUserIsAdmin):?>
            <div class="nav-link btn text-start active"
              hx-get="<?= COMPONENTS_URL . 'admin_dashboard_content.php' ?>"
              hx-target="#main"
              hx-swap="innerHTML"
              hx-trigger="load">
            </div>
          <?php else:?>
            <div class="nav-link btn text-start active"
              hx-get="<?= COMPONENTS_URL . 'teacher_dashboard_content.php' ?>"
              hx-target="#main"
              hx-swap="innerHTML"
              hx-trigger="load">
            </div>
          <?php endif?>
        </div>
      </div>
    </header>
  </div>

  <script src="https://unpkg.com/htmx.org@1.9.10"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    /* ---------- SweetAlert confirm for hx-confirm ---------- */
    document.body.addEventListener('htmx:confirm', function (evt) {
      // No hx-confirm on this element (e.g. sidebar links): let the request go through untouched
      if (!evt.detail.question) return;

      evt.preventDefault();

      Swal.fire({
        title: 'Confirm',
        text: evt.detail.question,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
      }).then(function (result) {
        if (result.isConfirmed) {
          evt.detail.issueRequest(true);
        }
      });
    });

    /* ---------- SweetAlert confirm for logout ---------- */
    document.getElementById('logoutLink').addEventListener('click', function (e) {
      e.preventDefault();
      const href = this.href;
      Swal.fire({
        title: 'Log out?',
        text: 'Are you sure you want to logout?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
      }).then(function (result) {
        if (result.isConfirmed) window.location.href = href;
      });
    });
  </script>

  <script>
    /* ---------- Sidebar: push on desktop, slide-out drawer on phones / iPad portrait ---------- */
    const sidebar = document.getElementById('pushSidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const desktopQuery = window.matchMedia('(min-width: 992px)');

    function syncSidebarState() {
      const isDesktop = desktopQuery.matches;
      const expanded = isDesktop
        ? !sidebar.classList.contains('collapsed')
        : sidebar.classList.contains('open');
      sidebarToggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      sidebarBackdrop.classList.toggle('show', !isDesktop && expanded);
    }

    function toggleSidebar() {
      sidebar.classList.toggle(desktopQuery.matches ? 'collapsed' : 'open');
      syncSidebarState();
    }

    function closeMobileSidebar() {
      sidebar.classList.remove('open');
      syncSidebarState();
    }

    sidebarBackdrop.addEventListener('click', closeMobileSidebar);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMobileSidebar();
    });
    // Rotating an iPad / resizing the window: drop the drawer so it never gets stuck open
    desktopQuery.addEventListener('change', closeMobileSidebar);
    syncSidebarState();

    function setActive(el) {
      document.querySelectorAll('#pushSidebar .nav-link').forEach(link => {
        link.classList.remove('active');
      });
      el.classList.add('active');

      // Close the drawer after picking a page on small screens
      if (!desktopQuery.matches) closeMobileSidebar();
    }

    /* ---------- Dark mode ---------- */
    const themeToggleBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const themeMeta = document.querySelector('meta[name="theme-color"]');
    const systemDarkQuery = window.matchMedia('(prefers-color-scheme: dark)');

    function applyTheme(theme) {
      const isDark = theme === 'dark';
      document.documentElement.setAttribute('data-bs-theme', theme);
      themeIcon.className = 'bi ' + (isDark ? 'bi-sun-fill' : 'bi-moon-stars-fill');
      const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
      themeToggleBtn.setAttribute('aria-label', label);
      themeToggleBtn.title = label;
      if (themeMeta) themeMeta.setAttribute('content', isDark ? '#212529' : '#ffffff');
    }

    themeToggleBtn.addEventListener('click', function () {
      const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      try { localStorage.setItem('srms-theme', next); } catch (e) {}
      applyTheme(next);
    });

    // Follow the device's light/dark setting until the user picks one manually
    systemDarkQuery.addEventListener('change', function (e) {
      let saved = null;
      try { saved = localStorage.getItem('srms-theme'); } catch (err) {}
      if (!saved) applyTheme(e.matches ? 'dark' : 'light');
    });

    applyTheme(document.documentElement.getAttribute('data-bs-theme'));
  </script>
</body>
</html>