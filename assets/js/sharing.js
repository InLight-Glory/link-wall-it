/**
 * Copies the given text to the user's clipboard.
 * Also provides visual feedback to the user.
 * @param {string} text The text to copy.
 * @param {HTMLElement} element The element that was clicked, for visual feedback.
 */
function copyToClipboard(text, element) {
    navigator.clipboard.writeText(text).then(function() {
        const originalText = element.innerText;
        element.innerText = 'Copied!';
        setTimeout(() => {
            element.innerText = originalText;
        }, 2000);
    }, function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy text.');
    });
}

/**
 * Opens a new popup window to share a URL on Twitter.
 * @param {string} url The URL to share.
 * @param {string} text The text to include in the tweet.
 */
function shareToTwitter(url, text) {
    const twitterUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
    window.open(twitterUrl, '_blank', 'width=600,height=400');
}

/**
 * Opens a new popup window to share a URL on Facebook.
 * @param {string} url The URL to share.
 */
function shareToFacebook(url) {
    const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
    window.open(facebookUrl, '_blank', 'width=600,height=400');
}
