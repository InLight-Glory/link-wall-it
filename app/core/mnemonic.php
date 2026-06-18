<?php

define('WORDLIST_FILE', __DIR__ . '/wordlist.txt');

function get_wordlist() {
    if (!file_exists(WORDLIST_FILE)) {
        return [];
    }
    // Read file into array, trim newlines
    return file(WORDLIST_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

/**
 * Generates a 12-word recovery phrase.
 *
 * @return string The recovery phrase (space separated).
 */
function generate_recovery_phrase() {
    $words = get_wordlist();
    if (count($words) < 2048) {
        return false; // Error
    }

    $phrase_array = [];
    for ($i = 0; $i < 12; $i++) {
        // Use random_int for CSPRNG
        $index = random_int(0, count($words) - 1);
        $phrase_array[] = $words[$index];
    }

    return implode(' ', $phrase_array);
}
?>
