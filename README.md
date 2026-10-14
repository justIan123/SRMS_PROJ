# Student Record Management System (SRMS)

A multi-platform Student Record Management System
efficient record-keeping, search, filtering, user management, and performance tracking. 
Engineered with flexibility in mind, the application supports offline desktop execution, mobile packaging, and web hosting deployments.

---

#Features

- **Multi-Platform Deployment Support:**
  - **Web:** Lightweight architecture with clean `.htaccess` URL routing optimized for shared hosting platforms like **InfinityFree**.
  - **Desktop:** Offline standalone application packaging using **PHPDesktop**.
  - **Mobile:** Android APK compilation and portable web execution via **HopWeb**.
- **Interactive UI with HTMX:** Seamless, single-page application (SPA) experience using **HTMX** for asynchronous form submissions and dynamic filtering without full page reloads.
- **Flexible Database Integration:**
  - **SQLite:** Primary lightweight embedded database for offline, portable, and low-overhead environments.
  - **MySQL (XAMPP / phpMyAdmin):** Full support for standard client-server web database setups.
  - **Supabase (PostgreSQL):** Prepared structural integration for future cloud database synchronization and remote backup.
- **Security & User Management:**
  - Secure PDO database transactions to prevent SQL injection.
  - Session-based Role-Based Access Control (RBAC) with granular user management CRUD operations.

---

## Technology Stack

| Domain | Technology |
| :--- | :--- |
| **Backend** | PHP (PDO Data Abstraction, Front Controller Pattern) |
| **Frontend** | HTML5, CSS3, Bootstrap 5, HTMX |
| **Databases** | SQLite (Primary/Main), MySQL (XAMPP), Supabase PostgreSQL (Cloud Target) |
| **Packaging & Hosts** | PHPDesktop (Windows Desktop), HopWeb (Android APK), InfinityFree (Web) |

---

## System Architecture & Routing

```text
SRMS_PROJ/
├── public/           # Public web root (index.php, CSS, JS, HTMX assets)
│   └── .htaccess     # URL rewrite rules for clean routes
├── app/              # Application core logic, views, and controllers
├── database/         # SQLite DB files, schema migrations, and SQL dumps
└── config/           # Database connections and app environment configs
