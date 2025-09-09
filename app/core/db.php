<?php

// Define the path to the database file, relative to this file.
define('DB_FILE', __DIR__ . '/../../data/database.json');

/**
 * Reads and returns the entire database from the JSON file.
 *
 * @return array The decoded JSON database as an associative array. Returns an empty array on failure.
 */
function get_db() {
    if (!file_exists(DB_FILE)) {
        error_log('Database file not found at: ' . DB_FILE);
        return [];
    }

    $json_data = file_get_contents(DB_FILE);
    if ($json_data === false) {
        error_log('Failed to read database file at: ' . DB_FILE);
        return [];
    }

    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        return [];
    }

    return $data;
}

/**
 * Saves the provided data array to the JSON database file.
 *
 * @param array $data The associative array of data to be saved.
 * @return bool True on success, false on failure.
 */
function save_db(array $data) {
    // JSON_PRETTY_PRINT makes the JSON file human-readable.
    $json_data = json_encode($data, JSON_PRETTY_PRINT);

    if ($json_data === false) {
        error_log('JSON encode error: ' . json_last_error_msg());
        return false;
    }

    // Use LOCK_EX to prevent race conditions during file writing.
    $result = file_put_contents(DB_FILE, $json_data, LOCK_EX);

    if ($result === false) {
        error_log('Failed to write to database file at: ' . DB_FILE);
        return false;
    }

    return true;
}
