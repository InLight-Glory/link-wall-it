# Beta Release Todo List

This document tracks the remaining tasks required to release **Link-Wall-It** as a public beta.

### 🚧 Remaining Tasks

- [ ] **Stripe Integration**
  - [x] Add admin settings for Stripe API keys.
  - [x] Update Admin UI to set prices for Walls.
  - [ ] Implement frontend payment flow (Stripe Checkout).
  - Create backend webhook handler for payment confirmation.

- [x] **Installation Wizard**
  - [x] Create a guided setup script (`install.php`) for first-time users.
  - [x] Allow configuration of Site Title and Admin Account (username/password).
  - [x] Ensure `data/database.json` is initialized correctly.

- [x] **Testing**
  - [x] Implement unit tests for core logic (`app/core/functions.php`).
  - [x] Add integration tests for critical flows (Login, CRUD operations).

- [x] **Documentation**
  - [x] Write a User Manual for the admin interface.
  - [x] Create Developer Guides for contributing.

### ✅ Completed for Beta

- [x] **Admin Authentication**
  - Secure admin pages with `require_login()`.
  - Implement Login/Logout functionality.
  - Secure password storage (Argon2id).
