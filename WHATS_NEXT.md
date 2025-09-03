# 🎯 What's Next?

This file tracks the immediate development focus. Based on our updated roadmap, the next priority is to implement the "Code Lock" feature from **Phase 4**.

## Current Focus: Code Lock

Our immediate goal is to implement a simple password/code lock feature. This will be a distinct and simpler alternative to the Codelist feature.

While we already have a "Codelist" feature that allows for multiple access codes, the "Code Lock" as described in the original brief implies a single, simple password or code for a Wall. This can be implemented by leveraging our existing 'password' access control type.

The main tasks will be:
1.  **Review existing functionality:** Ensure the current 'password' protection system fully meets the "Code Lock" requirement.
2.  **Update UI text if needed:** Change labels in the admin panel from "Password" to "Password / Code Lock" to make the feature's purpose clearer to the user.
3.  **Confirm with user:** Verify that the existing password functionality is sufficient for the "Code Lock" task on the roadmap.

This approach avoids re-implementing similar features and keeps the codebase clean.
