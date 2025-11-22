# Link-Wall-It User Manual

Welcome to **Link-Wall-It**, your self-hosted link curation platform. This guide will help you set up and manage your link walls.

## 🚀 Installation

1.  **Deploy the Code:** Upload the files to your PHP-enabled web server.
2.  **Start the Wizard:** Visit your site's URL (e.g., `http://yourdomain.com/`). You will be automatically redirected to the Installation Wizard (`/install.php`).
3.  **Configure:** Enter your desired Site Title, Admin Username, and Admin Password.
4.  **Finish:** Click "Install". You will be redirected to the Admin Login page.

*Note: The installer creates a `data/installed.lock` file. If you need to reinstall, delete this file manually from the server.*

## 🔐 Admin Panel

Access the admin panel at `/admin/` (or via the "Admin" link in the footer). Log in with the credentials you created during installation.

### Dashboard
The main dashboard displays your **Buildings**.
- **Create Building:** Enter a name and click "Create".
- **Manage:** Click "Manage Sides" on a building to drill down.

### Organization Hierarchy
Link-Wall-It uses a spatial metaphor:
1.  **Building:** The top-level category (e.g., "My Projects").
2.  **Side:** Each Building has up to 4 Sides (e.g., "Side A", "Side B").
3.  **Wall:** Each Side can contain multiple Walls. A Wall is a list of links.
4.  **Link:** The actual content (Title, URL, Description, Image).

### Managing Content
Navigate through the hierarchy to find the level you want to edit.
- **Breadcrumbs:** Use the navigation links at the top of the page to go back up the hierarchy.
- **Edit/Delete:** Use the "Edit" and "Delete" links next to any item. *Warning: Deleting a container (like a Building) deletes all content inside it.*

### 🛡️ Wall Security
When managing a **Wall**, you can restrict access:
- **Public:** Everyone can see the links.
- **Password / Code:** Visitors must enter a single password to view the wall.
- **Codelist:** Visitors must enter one of the valid codes from your list (useful for one-time access tokens).
- **Payment (Stripe):** *[Coming Soon]* Requires payment to access.

### ⚙️ Settings
Click "Settings" in the admin header to:
- **General:** Change the Site Title and Description.
- **Stripe Integration:** Enter your Stripe API keys (Publishable and Secret) for monetization features.
- **Change Password:** Update your admin login password.
