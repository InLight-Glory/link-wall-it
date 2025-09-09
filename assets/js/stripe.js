/**
 * Handles the Stripe checkout process.
 * 1. Fetches the checkout session ID from our server.
 * 2. Redirects the user to Stripe's checkout page.
 *
 * @param {string} publishableKey The Stripe publishable key.
 * @param {string} wallId The ID of the wall the user is trying to access.
 */
function goToStripeCheckout(publishableKey, wallId) {
    const stripe = Stripe(publishableKey);

    fetch('create_checkout_session.php?wall_id=' + wallId)
        .then(function(response) {
            return response.json();
        })
        .then(function(session) {
            if (session.error) {
                throw new Error(session.error);
            }
            return stripe.redirectToCheckout({ sessionId: session.id });
        })
        .then(function(result) {
            // If `redirectToCheckout` fails due to a browser popup blocker or
            // other issue, we can display the error message here.
            if (result.error) {
                alert(result.error.message);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('Could not initiate payment. Please try again.');
        });
}
