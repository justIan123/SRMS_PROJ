https://github.com/justIan123/SRMS_PROJ

LOCKED DB ?? PRAGMA journal_mode = WAL;

Supabase
DB  :SRMS_DB
Pass:SRMS_@SaintRoche_2026!

my-php-app/
├── config/                  # Server & database configurations
│   ├── app.php              # App constants & path definitions
│   └── database.php         # PDO / MySQL connection setup
│
├── public/                  # Publicly accessible web root
│   ├── assets/              # Client-side static resources
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   ├── .htaccess            # Server rewrite rules (mod_rewrite)
│   └── index.php            # Single entry point (Front Controller)
│
├── src/                     # Core application logic & handlers (non-public)
│   ├── Auth/                # Feature module: Login, signup, session logic
│   │   ├── auth-handler.php
│   │   └── session.php
│   ├── Database/            # Database helper functions & query handlers
│   │   └── db-functions.php
│   └── Helpers/             # Utility functions & formatting helpers
│       └── functions.php
│
├── templates/               # UI views & page layouts
│   ├── includes/            # Reusable components (Header, Footer, Nav)
│   │   ├── header.php
│   │   └── footer.php
│   ├── auth/                # Views grouped by feature
│   │   ├── login.php
│   │   └── register.php
│   └── home.php             # Main homepage template
│
├── storage/                 # Protected server-side storage
│   └── logs/                # Application error logs
│       └── app.log
│
└── README.md