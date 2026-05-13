# kwnta

A web-based group expense tracker that simplifies shared spending by automatically calculating balances between members.

Made as a web development project demonstrating CRUD operations. Built for situations where multiple people spend together — trips, food outings, dorm expenses, or school projects — kwnta lets you record expenses, split them equally, and always know who owes what.

---

## Features

- **Group Management** — Create groups, add members by email, and assign admin roles
- **Expense Recording** — Log expenses with description, amount, payer, and split members
- **Equal Splitting** — Automatically divides expenses equally among selected members
- **Balance Tracking** — Calculates per-person net balances across all groups
- **Paid Status** — Admins can mark individual splits as paid or unpaid
- **Leave Requests** — Members can request to leave a group; admins approve or reject
- **Group Archiving** — Admins can archive groups without deleting any data
- **Settings** — Update profile, change password, view account stats, delete account

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, vanilla JS |
| Backend | PHP 8+ |
| Database | MariaDB / MySQL 8+ |
| Auth | PHP sessions + `password_hash` |

---

## Project Structure

```
kwnta/
├── config/
│   ├── db.php                    # PDO database connection
│
├── public/                       # Document root (point your vhost here)
│   ├── index.php                 # Login page
│   ├── register.php              # Register page
│   ├── dashboard.php             # Overview
│   ├── groups.php                # Groups list
│   ├── group.php                 # Group detail + expenses
│   ├── expenses.php              # All expenses across groups
│   ├── balances.php              # Net balances across groups
│   ├── settings.php              # Profile, security, account
│   ├── assets/
│   │   └── css/
│   │       ├── root.css          # Global variables + resets
│   │       ├── layout.css        # Sidebar + page layout
│   │       ├── login.css         # Auth pages
│   │       ├── dashboard.css     # Dashboard-specific styles
│   │       ├── groups.css        # Shared components + groups
│   │       ├── expenses.css      # Expense + split styles
│   │       ├── balances.css      # Balance page styles
│   │       └── settings.css      # Settings page styles
│   └── handlers/
│       ├── login-handler.php
│       ├── register-handler.php
│       ├── group-handler.php     # create, add_member, archive, leave
│       ├── expense-handler.php   # create, delete
│       ├── split-handler.php     # toggle_paid, promote_admin
│       ├── settings-handler.php  # update_profile, update_password, delete_account
│       └── logout-handler.php
│
└── src/
    ├── middleware/
    │   └── AuthMiddleware.php    # Session guard
    ├── controllers/
    │   └── AuthController.php    # Login + register logic
    ├── services/
    │   ├── AuthService.php       # DB-level auth queries
    │   ├── GroupService.php      # Group + member + leave request queries
    │   ├── ExpenseService.php    # Expense + split + balance queries
    │   └── UserService.php       # Profile + password + account deletion
    └── views/
        ├── layout.php            # Shell template (sidebar + head)
        └── partials/
            └── sidebar.php       # Navigation sidebar
```

---

## Live Demo

Available at [kwnta.dcism.org](https://kwnta.dcism.org)

---

## Installation

### Requirements

- PHP 8.0 or higher
- MariaDB 10.5+ or MySQL 8+
- A local server (XAMPP, Laragon, MAMP, or similar)

### Steps

**1. Clone or download the project**

```bash
git clone https://github.com/yourname/kwnta.git
cd kwnta
```

**2. Point your vhost document root to `/public`**

If you're using XAMPP with a virtual host, add this to `httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName kwnta.test
    DocumentRoot "/path/to/kwnta/public"
    <Directory "/path/to/kwnta/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**3. Create the database**

```sql
CREATE DATABASE kwnta CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

**4. Set up the database tables**

Create the tables using your database client (phpMyAdmin, TablePlus, DBeaver, etc.). The table structure is documented in the Database Schema section below.

**5. Configure the database connection**

Create `config/db.php`:

```php
<?php
$host   = 'localhost';
$dbname = 'kwnta';
$user   = 'root';
$pass   = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
```

**6. Visit the app**

```
http://kwnta.test
```

Register an account and start tracking.

---

## Database Schema

```
users
  id, firstname, lastname, email, password, created_at

groups
  id, name, icon, created_by → users, status, archived_at, created_at

group_members
  id, group_id → groups, user_id → users, role (admin|member), joined_at

expenses
  id, group_id → groups, paid_by → users, description, amount, created_at

expense_splits
  id, expense_id → expenses, user_id → users, share, paid

leave_requests
  id, group_id → groups, user_id → users, status (pending|approved|rejected),
  message, created_at, resolved_at, resolved_by → users
```

---

## Roles & Permissions

| Action | Member | Admin |
|---|---|---|
| View group & expenses | ✓ | ✓ |
| Add expenses | ✓ | ✓ |
| Mark splits as paid | ✗ | ✓ |
| Add members | ✗ | ✓ |
| Promote members to admin | ✗ | ✓ |
| Archive / restore group | ✗ | ✓ |
| Approve / reject leave requests | ✗ | ✓ |
| Request to leave group | ✓ | ✗ |



---

## Notes

- The app uses PHP sessions for authentication. `session_start()` is called once per entry point in the handler files.
- All DB queries use PDO prepared statements — no raw string interpolation in SQL.
- `groups` is a reserved word in MySQL/MariaDB so all queries wrap it in backticks.
- The `LIMIT` clause in `getRecentExpenses` uses string interpolation (not a bound parameter) because PDO treats bound params as strings which MySQL rejects in `LIMIT`.
