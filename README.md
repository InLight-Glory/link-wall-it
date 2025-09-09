# Link-Wall-It

![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)

**Link-Wall-It** is an open-source, self-hostable web application that provides a powerful and intuitive platform for you to curate, organize, share, and even monetize collections of links. Think of it as your personal, self-hosted alternative to services like Linktree, Stan Store, or Beacons.ai, where you have complete control over your data and your platform.

## The Vision: Your Space, Your Links, Your Rules

In a world of closed-off, proprietary platforms, Link-Wall-It is built on the principle of empowerment. We believe creators, educators, and businesses should own their online presence without being locked into a specific service. By being open-source and self-hostable, Link-Wall-It gives you the freedom to customize, extend, and integrate your link collections in any way you see fit.

We use a simple spatial metaphor for organization:
*   **Buildings:** Your top-level categories (e.g., "My Projects", "Social Media", "Educational Resources").
*   **Sides:** Four sub-categories within each Building (e.g., "Work in Progress", "Completed Projects").
*   **Walls:** Curated lists of links that live on a Side. This is where you share your content.

## ✨ Key Features

*   **100% Open Source & Self-Hosted:** No monthly fees, no hidden charges. You host it on your own server.
*   **Full Data Ownership:** Your data lives in a simple `database.json` file, which you control completely.
*   **Intuitive Content Structure:** The "Building -> Side -> Wall" hierarchy makes organizing links a breeze.
*   **Simple Admin Dashboard:** A clean interface for all your CRUD (Create, Read, Update, Delete) operations.
*   **Built-in Monetization:** Lock Walls with a simple access code or integrate your Stripe account to sell access.
*   **Easy Installation:** A WordPress-like installation wizard to get you up and running in minutes.
*   **Sharable URLs:** Every Building, Side, and Wall has its own unique URL for easy sharing.

## 💻 Tech Stack

*   **Backend:** PHP (vanilla, no frameworks)
*   **Database:** JSON file (simple, portable, and human-readable)
*   **Frontend:** HTML, CSS, and vanilla JavaScript (keeping it simple and lightweight)

## 🚀 Getting Started

*(This section will be updated as the project progresses)*

To get a local copy up and running, follow these simple steps.

### Prerequisites

*   A web server with PHP (version 8.0+ recommended).
*   The `php-json` extension enabled.

### Installation

1.  Clone the repo:
    ```sh
    git clone https://github.com/your-username/link-wall-it.git
    ```
2.  Navigate to the project directory:
    ```sh
    cd link-wall-it
    ```
3.  (Coming Soon) Follow the on-screen installation wizard by visiting the site in your browser. For now, the `data/database.json` file is pre-configured with a default setup.

## 🤝 How to Contribute

We welcome contributions of all kinds! Whether you're a developer, a designer, or a documentation writer, you can help make Link-Wall-It better.

Here's how you can get involved:

1.  **Fork the Project:** Click the 'Fork' button at the top right of this page.
2.  **Clone your Fork:** `git clone https://github.com/your-username/link-wall-it.git`
3.  **Create your Feature Branch:** `git checkout -b feature/AmazingNewFeature`
4.  **Commit your Changes:** `git commit -m 'Add some AmazingNewFeature'`
5.  **Push to the Branch:** `git push origin feature/AmazingNewFeature`
6.  **Open a Pull Request:** Go back to the original repository and open a pull request.

Please make sure your code adheres to our coding standards and that you provide a clear description of the changes you've made.

## 📜 License

Distributed under the MIT License. See `LICENSE` for more information.
