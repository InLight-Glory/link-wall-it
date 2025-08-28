<?php

// Require the database helper functions
require_once __DIR__ . '/db.php';

/**
 * Creates a new building and saves it to the database.
 *
 * @param string $name The name of the new building.
 * @return bool True on success, false on failure.
 */
function create_building($name) {
    $db = get_db();

    $new_building = [
        'id' => 'b_' . uniqid(), // Prefix 'b_' for clarity
        'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), // Sanitize input
        'sides' => []
    ];

    $db['buildings'][] = $new_building;

    return save_db($db);
}

/**
 * Retrieves all buildings from the database.
 *
 * @return array A list of all building records.
 */
function get_all_buildings() {
    $db = get_db();
    // Return buildings array, or an empty array if it doesn't exist
    return $db['buildings'] ?? [];
}

/**
 * Retrieves a single building by its unique ID.
 *
 * @param string $id The ID of the building to retrieve.
 * @return array|null The building data as an array, or null if not found.
 */
function get_building($id) {
    $buildings = get_all_buildings();
    foreach ($buildings as $building) {
        if ($building['id'] === $id) {
            return $building;
        }
    }
    return null;
}

/**
 * Updates a building's name given its ID.
 *
 * @param string $id      The ID of the building to update.
 * @param string $newName The new name for the building.
 * @return bool True on success, false on failure or if building not found.
 */
function update_building($id, $newName) {
    $db = get_db();
    $building_found = false;

    foreach ($db['buildings'] as $key => $building) {
        if ($building['id'] === $id) {
            $db['buildings'][$key]['name'] = htmlspecialchars($newName, ENT_QUOTES, 'UTF-8');
            $building_found = true;
            break;
        }
    }

    if ($building_found) {
        return save_db($db);
    }

    return false;
}

/**
 * Deletes a building from the database by its ID.
 *
 * @param string $id The ID of the building to delete.
 * @return bool True on success, false on failure or if building not found.
 */
function delete_building($id) {
    $db = get_db();
    $initial_count = count($db['buildings'] ?? []);

    // Filter out the building with the matching ID
    $db['buildings'] = array_filter($db['buildings'] ?? [], function ($building) use ($id) {
        return $building['id'] !== $id;
    });

    // Re-index the array to ensure it stays a JSON array
    $db['buildings'] = array_values($db['buildings']);

    // Check if a building was actually removed
    if (count($db['buildings']) < $initial_count) {
        return save_db($db);
    }

    return false;
}
