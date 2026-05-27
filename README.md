# kwnta

A web app for tracking shared group expenses — log what was spent, split it equally, and always know who owes who.

Made as a web development project demonstrating CRUD operations. Available at [kwnta.dcism.org](https://kwnta.dcism.org)

---

## Tech Stack

| Layer      | Technology                       |
| ---------- | -------------------------------- |
| Frontend   | HTML, CSS, vanilla JS            |
| Backend    | PHP 8+                           |
| Database   | MariaDB 10.5+ / MySQL 8+         |
| Auth       | PHP sessions + `password_hash`   |
| Icons      | Google Material Symbols Outlined |
| Typography | DM Sans (headings), Inter (body) |

---

## Features

- Group creation with icon picker and role-based access (admin / member)
- Expense recording with equal splitting among selected members
- Per-split paid status tracking, toggled by group admins
- Balance calculation — who owes who and how much, per group and across all groups
- Leave request workflow — members request to leave, admins approve or reject
- Group archiving — hide groups without deleting any data
- Paginated expense lists (10 per page) with SQL LIMIT/OFFSET
- Search on expenses, groups, and balances pages
- Expense detail modal with full split breakdown
- Add member modal with live user search
- Account soft delete with a 30-day recovery window
- Full-screen account recovery page
- Generic error page — no stack traces shown to users
- Fully responsive UI, no CSS framework

---

## Project Structure

```
kwnta/
├── config/
│   ├── db.php                    # PDO connection (not in repo — create manually)
│   └── schema.sql                # Full MariaDB-compatible schema
│
├── public/                       # Document root
│   ├── index.php                 # Login
│   ├── register.php              # Register
│   ├── dashboard.php             # Overview
│   ├── groups.php                # Groups list with search
│   ├── group.php                 # Group detail, paginated expenses, search
│   ├── expenses.php              # All expenses across groups with search
│   ├── balances.php              # Net balances with search
│   ├── settings.php              # Profile, security, account
│   ├── account-recovery.php      # 30-day deletion recovery screen
│   ├── error.php                 # Generic error page
│   ├── assets/
│   │   └── css/
│   │       ├── root.css          # Design tokens + resets
│   │       ├── layout.css        # Sidebar + page shell
│   │       ├── login.css         # Auth pages
│   │       ├── dashboard.css     # Dashboard
│   │       ├── groups.css        # Shared components + groups
│   │       ├── expenses.css      # Expenses + splits
│   │       ├── balances.css      # Balances page
│   │       ├── settings.css      # Settings page
│   │       └── recovery.css      # Account recovery page
│   └── handlers/
│       ├── login-handler.php
│       ├── register-handler.php
│       ├── logout-handler.php
│       ├── group-handler.php     # create, add_member, archive, leave requests
│       ├── expense-handler.php   # create, delete
│       ├── split-handler.php     # toggle_paid, promote_admin
│       ├── settings-handler.php  # update_profile, update_password, delete_account
│       └── recover-handler.php   # restore account within 30-day window
│
└── src/
    ├── middleware/
    │   └── AuthMiddleware.php    # Session guard + recovery redirect
    ├── controllers/
    │   └── AuthController.php    # Login + register
    ├── services/
    │   ├── AuthService.php       # DB-level auth queries
    │   ├── GroupService.php      # Groups, members, leave requests, archive
    │   ├── ExpenseService.php    # Expenses, splits, balances, pagination, search
    │   └── UserService.php       # Profile, password, soft delete, recovery
    └── views/
        ├── layout.php            # Shell template
        └── partials/
            └── sidebar.php       # Navigation sidebar
```

---

## Database Schema

```
users
  id, email, password, firstname, lastname,
  deleted_at (NULL = active, set = pending deletion),
  created_at

groups
  id, name, icon, created_by → users,
  status (active | archived), archived_at, created_at

group_members
  id, group_id → groups, user_id → users,
  role (admin | member), joined_at

expenses
  id, group_id → groups, paid_by → users,
  description, amount, created_at

expense_splits
  id, expense_id → expenses, user_id → users,
  share, paid (0 | 1)

leave_requests
  id, group_id → groups, user_id → users,
  status (pending | approved | rejected),
  message, resolved_by → users, resolved_at, created_at
```

---

## Installation

**Requirements**

- PHP 8.0+
- MariaDB 10.5+ or MySQL 8+

**Steps**

**1. Clone the repository**

```bash
git clone https://github.com/yourname/kwnta.git
cd kwnta
```

**2. Create the database**

Create a new database in phpMyAdmin or your DB client, then import the schema:

```bash
mysql -u root -p kwnta < config/schema.sql
```

**3. Create `config/db.php`**

```php
<?php

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    include __DIR__ . '/../public/error.php';
    exit();
});

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$host   = 'localhost';
$dbname = 'kwnta';
$user   = 'root';
$pass   = '';

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    include __DIR__ . '/../public/error.php';
    exit();
}
```

**4. Start the PHP built-in server**

```bash
php -S localhost:8000 -t public
```

**5. Visit the app**

```
http://localhost:8000
```

Register an account to get started.

---

## Roles & Permissions

| Action                          | Member | Admin |
| ------------------------------- | ------ | ----- |
| View group and expenses         | ✓      | ✓     |
| Add expenses                    | ✓      | ✓     |
| Delete expenses                 | ✗      | ✓     |
| Mark splits as paid             | ✗      | ✓     |
| Add members                     | ✗      | ✓     |
| Promote members to admin        | ✗      | ✓     |
| Archive / restore group         | ✗      | ✓     |
| Approve / reject leave requests | ✗      | ✓     |
| Request to leave group          | ✓      | ✗     |

---

## Account Deletion

Deleting an account does not immediately remove data. Instead:

1. `deleted_at` is set to the current timestamp
2. The user is redirected to `account-recovery.php`
3. On every page load, `AuthMiddleware` detects the pending deletion and forces the recovery screen — the user cannot access any other page
4. The recovery screen shows how many days remain and offers a single "Recover My Account" button
5. If recovered, `deleted_at` is set back to `NULL` and full access is restored
6. After 30 days, `UserService::purgeExpiredAccounts()` can be scheduled via cron to permanently remove the record

---

## Notes

- `groups` is a reserved word in MariaDB/MySQL — all queries wrap it in backticks
- `LIMIT` uses string interpolation instead of a bound parameter because PDO binds integers as strings which MariaDB rejects in `LIMIT` clauses
- All error details are hidden from users — handlers redirect with generic error codes only, and `db.php` registers a global exception handler that shows `error.php` instead of a stack trace
- `config/db.php` is not included in the repository
- `session_start()` is called once per entry point in the handler files — never inside controller methods
