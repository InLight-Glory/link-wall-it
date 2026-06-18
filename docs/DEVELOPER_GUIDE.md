# Link-Wall-It Developer Guide

This document provides technical details for developers looking to contribute to or customize Link-Wall-It.

## 🛠️ Tech Stack
- **Backend:** PHP (Vanilla, 8.0+ recommended).
- **Database:** JSON Flat-file (`data/database.json`).
- **Frontend:** HTML5, CSS3, Vanilla JavaScript.
- **Testing:** PHP scripts + Shell runner.

## 📂 Directory Structure

```
/
├── admin/              # Admin interface files (protected)
├── app/
│   └── core/           # Core logic (db, auth, encryption)
├── assets/             # Images, CSS, JS
├── data/               # Database storage (protected by .htaccess)
├── docs/               # Documentation
├── tests/              # Test suite
├── index.php           # Public entry point
├── install.php         # Installation wizard
└── ...
```

## 💾 Database Schema
The `data/database.json` file contains the entire application state.

```json
{
    "settings": { ... },
    "users": [
        { "username": "admin", "password_hash": "...", "salt": "..." }
    ],
    "buildings": [ { "id": "b_...", "name": "..." } ],
    "sides": [ { "id": "s_...", "building_id": "b_...", ... } ],
    "walls": [
        {
            "id": "w_...",
            "side_id": "s_...",
            "access_control": {
                "type": "password",
                "password": { "hash": "...", "salt": "..." }
            }
        }
    ],
    "links": [ ... ]
}
```

## 🔐 Authentication & Security
- **Sessions:** Standard PHP sessions (`session_start`).
- **Hashing:** Passwords and access codes are hashed using `argon2id` via `app/core/encryption.php`.
- **Protection:** Admin pages enforce `require_login()` which redirects to `admin/login.php` if the session is invalid.
- **Data Security:** The `data/` directory is protected via `.htaccess` (`Deny from all`) to prevent direct web access.

## 🧪 Testing
The project includes a custom test runner.
1.  **Run Tests:** `./tests/run_tests.sh`
    - This script automatically backs up your `database.json`, runs all `tests/*.php`, and then restores the database.
2.  **Write Tests:** Create a new `.php` file in `tests/` that requires `app/core/functions.php` and asserts logic.

## 📝 Contributing
1.  Check `TODO.md` for open tasks.
2.  Create a feature branch.
3.  Ensure all tests pass (`./tests/run_tests.sh`).
4.  Submit a Pull Request.
