# 🎯 What's Next?

This file tracks the immediate development focus. With the completion of the Stripe Integration, the next priority is the final task in **Phase 4**: the **Installation Wizard**.

## Current Focus: Installation Wizard

Our immediate goal is to build a user-friendly, guided installation script to simplify the setup process for new users. This is a critical step for making the application accessible to a non-technical audience.

The main tasks will be:
1.  **Create an `install/` directory:** This will house the installation script and assets.
2.  **Server Requirement Checks:** The installer will check for the correct PHP version, required extensions (like `curl`, `mbstring`), and file permissions for `data/database.json`.
3.  **Admin Account Creation:** The installer will present a form for the user to create their initial administrator account (username and password).
4.  **Database Initialization:** The script will create the `database.json` file with the correct initial structure and the new admin user's credentials.
5.  **Cleanup:** After a successful installation, the script should provide instructions to delete the `install/` directory for security.
