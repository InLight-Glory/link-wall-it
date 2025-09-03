# 🎯 What's Next?

This file tracks the immediate development focus. Based on our updated roadmap, the next priority is to implement the "Stripe Integration" feature from **Phase 4**.

## Current Focus: Stripe Integration

Our immediate goal is to build out the functionality to allow creators to monetize their walls using Stripe.

The main tasks will be:
1.  **Admin Settings:** Add a section in the admin panel for the user to securely save their Stripe API keys (Publishable and Secret Key).
2.  **UI for Monetization:** Update the "Wall Security" section in `admin/manage_wall.php` with a new 'Stripe' access type. This will allow the admin to set a price for accessing a wall.
3.  **Frontend Payment Form:** On the public `wall.php` page, if a wall is protected by Stripe, display a "Pay to Access" button that launches the Stripe Checkout flow.
4.  **Backend Webhook:** Create a backend endpoint to handle the `checkout.session.completed` event from Stripe to grant access to the user after a successful payment.

This is a large feature, and we will tackle it step-by-step, starting with the admin settings.
