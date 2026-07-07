# PPMIS — Project Progress and Management Information System
### DOST Provincial Office, Maasin City

---

## Tech Stack
- **Frontend:** HTML5, CSS3, Bootstrap 5, Vanilla JavaScript
- **Backend:** PHP 8 (PDO)
- **Database:** MySQL
- **Server:** Apache (XAMPP / WAMP / Laravel Herd)

---

## Folder Structure

```
ppmis/
├── .htaccess                    ← Apache security & config
├── login.php                    ← Login page
├── logout.php                   ← Session destroy
├── index.php                    ← Dashboard
│
├── config/
│   └── database.php             ← DB connection + constants
│
├── includes/
│   ├── header.php               ← Shared sidebar/topbar layout
│   ├── footer.php               ← Shared closing tags + scripts
│   ├── session.php              ← Auth helpers, CSRF, XSS escaping
│   └── upload.php               ← Secure file upload handler
│
├── assets/
│   ├── css/main.css             ← All styles
│   └── js/main.js               ← Toast, PDC modal, file preview, charts
│
├── database/
│   └── schema.sql               ← Full MySQL schema + seed data
│
├── modules/
│   ├── project/
│   │   ├── create.php           ← Approval Stage (start project)
│   │   ├── view.php             ← Project detail & document status
│   │   ├── stage_first_untagging.php
│   │   ├── stage_final_untagging.php
│   │   ├── stage_pre_refunding.php
│   │   └── stage_refunding.php
│   ├── progress/
│   │   └── index.php            ← Progress monitoring table
│   ├── financial/
│   │   └── report.php           ← Financial report + image preview
│   └── firms/
│       └── create.php           ← Add/manage proponent firms
│
├── api/
│   ├── submit_pdcs.php          ← PDC mass upload endpoint
│   ├── refund_action.php        ← Notify / Done toggle endpoint
│   └── get_pdcs.php             ← PDC list viewer (new tab)
│
└── uploads/
    ├── project_images/          ← Project banner images
    ├── submissions/             ← All stage document uploads
    └── pdcs/                    ← PDC image uploads
```

---

## Setup Instructions

### 1. Place files in web root
Copy the `ppmis/` folder into your Apache web root:
- XAMPP: `C:/xampp/htdocs/ppmis/`
- WAMP: `C:/wamp64/www/ppmis/`
- Linux: `/var/www/html/ppmis/`

### 2. Create the database
Open **phpMyAdmin** or MySQL CLI and run:
```sql
SOURCE /path/to/ppmis/database/schema.sql;
```

### 3. Configure DB connection
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ppmis');
define('DB_USER', 'root');       // ← your MySQL username
define('DB_PASS', '');           // ← your MySQL password
```

### 4. Set upload path
Make sure the `uploads/` directory is writable:
```bash
chmod -R 755 uploads/
```

Also update `UPLOAD_DIR` in `config/database.php` if your path differs:
```php
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '/ppmis/uploads/');  // ← adjust if not at web root
```

### 5. Access the system
Open your browser: **http://localhost/ppmis/**

**Default login:**
| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `Admin@123` |

> ⚠️ Change the default password after first login in production.

---

## Workflow Stages
| Step | Stage | Page |
|------|-------|------|
| 1 | Approval Stage | `modules/project/create.php` |
| 2 | 1st Untagging Stage | `modules/project/stage_first_untagging.php` |
| 3 | Final Untagging Stage | `modules/project/stage_final_untagging.php` |
| 4 | Pre-Refunding Submissions | `modules/project/stage_pre_refunding.php` |
| 5 | Refunding Stage | `modules/project/stage_refunding.php` |

---

## Security Features
- PDO prepared statements (SQL injection prevention)
- CSRF token on every form
- XSS prevention via `htmlspecialchars()`
- File type validation via `finfo` (not `$_FILES['type']`)
- File size limit: 10MB
- Allowed types: JPG, PNG, GIF, PDF
- PHP files blocked in uploads/ via `.htaccess`
- Session regeneration on login

---

## Default Admin Password Hash
The seeded password `Admin@123` uses `password_hash()` with `PASSWORD_BCRYPT, ['cost'=>12]`.
To generate a new hash in PHP:
```php
echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost' => 12]);
```
Then update the `users` table directly.
