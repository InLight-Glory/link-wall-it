<?php

// Require helper files
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/encryption.php';

// --- Building Functions ---

/**
 * Creates a new building.
 *
 * @param string $name The name of the new building.
 * @return bool True on success, false on failure.
 */
function create_building($name) {
    $db = get_db();

    $new_building = [
        'id' => 'b_' . uniqid(),
        'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    ];

    $db['buildings'][] = $new_building;

    if (save_db($db)) {
        return $new_building['id'];
    }
    return false;
}

/**
 * Retrieves all buildings.
 *
 * @return array A list of all building records.
 */
function get_all_buildings() {
    $db = get_db();
    return $db['buildings'] ?? [];
}

/**
 * Retrieves a single building by its ID.
 *
 * @param string $id The ID of the building.
 * @return array|null The building data, or null if not found.
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
 * Updates a building's name.
 *
 * @param string $id The ID of the building to update.
 * @param string $newName The new name.
 * @return bool True on success, false on failure.
 */
function update_building($id, $newName) {
    $db = get_db();
    $found = false;
    foreach ($db['buildings'] as &$building) {
        if ($building['id'] === $id) {
            $building['name'] = htmlspecialchars($newName, ENT_QUOTES, 'UTF-8');
            $found = true;
            break;
        }
    }

    if ($found) {
        return save_db($db);
    }

    return false;
}

/**
 * Deletes a building and all its descendants (sides, walls, links).
 *
 * @param string $id The ID of the building to delete.
 * @return bool True on success, false if building not found.
 */
function delete_building($id) {
    $db = get_db();
    $initial_building_count = count($db['buildings'] ?? []);

    // Find sides associated with the building
    $sides_to_delete_ids = [];
    foreach ($db['sides'] ?? [] as $side) {
        if ($side['building_id'] === $id) {
            $sides_to_delete_ids[] = $side['id'];
        }
    }

    // Find walls associated with those sides
    $walls_to_delete_ids = [];
    if (!empty($sides_to_delete_ids)) {
        foreach ($db['walls'] ?? [] as $wall) {
            if (in_array($wall['side_id'], $sides_to_delete_ids)) {
                $walls_to_delete_ids[] = $wall['id'];
            }
        }
    }

    // Filter all arrays, removing descendants of the building
    if (!empty($walls_to_delete_ids)) {
        $db['links'] = array_values(array_filter($db['links'] ?? [], function($link) use ($walls_to_delete_ids) {
            return !in_array($link['wall_id'], $walls_to_delete_ids);
        }));
    }

    if (!empty($sides_to_delete_ids)) {
        $db['walls'] = array_values(array_filter($db['walls'] ?? [], function($wall) use ($sides_to_delete_ids) {
            return !in_array($wall['side_id'], $sides_to_delete_ids);
        }));
    }

    $db['sides'] = array_values(array_filter($db['sides'] ?? [], function($side) use ($id) {
        return $side['building_id'] !== $id;
    }));

    $db['buildings'] = array_values(array_filter($db['buildings'] ?? [], function($building) use ($id) {
        return $building['id'] !== $id;
    }));

    // Check if a building was actually deleted before saving
    if (count($db['buildings'] ?? []) < $initial_building_count) {
        return save_db($db);
    }

    return false; // Building not found or no change made
}

// --- Side Functions ---

/**
 * Creates a new side for a given building.
 *
 * @param string $building_id The ID of the parent building.
 * @param string $name The name of the new side.
 * @return string|bool The new side's ID on success, false on failure.
 */
function create_side($building_id, $name) {
    $db = get_db();

    // Check if parent building exists
    if (!get_building($building_id)) {
        error_log("Attempted to create side for non-existent building ID: $building_id");
        return false;
    }

    // Enforce max 4 sides per building
    $sides_for_building = get_sides_for_building($building_id);
    if (count($sides_for_building) >= 4) {
        error_log("Attempted to create more than 4 sides for building ID: $building_id");
        return false;
    }

    $new_side = [
        'id' => 's_' . uniqid(),
        'building_id' => $building_id,
        'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    ];

    $db['sides'][] = $new_side;

    if (save_db($db)) {
        return $new_side['id'];
    }
    return false;
}

/**
 * Retrieves all sides for a specific building.
 *
 * @param string $building_id The ID of the building.
 * @return array A list of side records.
 */
function get_sides_for_building($building_id) {
    $db = get_db();
    $sides_for_building = [];
    foreach ($db['sides'] ?? [] as $side) {
        if ($side['building_id'] === $building_id) {
            $sides_for_building[] = $side;
        }
    }
    return $sides_for_building;
}

/**
 * Retrieves a single side by its ID.
 *
 * @param string $id The ID of the side.
 * @return array|null The side data, or null if not found.
 */
function get_side($id) {
    $db = get_db();
    foreach ($db['sides'] ?? [] as $side) {
        if ($side['id'] === $id) {
            return $side;
        }
    }
    return null;
}

/**
 * Updates a side's name.
 *
 * @param string $id The ID of the side to update.
 * @param string $newName The new name.
 * @return bool True on success, false on failure.
 */
function update_side($id, $newName) {
    $db = get_db();
    $found = false;
    // Note: passing $side by reference using &$side
    foreach ($db['sides'] as &$side) {
        if ($side['id'] === $id) {
            $side['name'] = htmlspecialchars($newName, ENT_QUOTES, 'UTF-8');
            $found = true;
            break;
        }
    }

    if ($found) {
        return save_db($db);
    }

    return false;
}

/**
 * Deletes a side and all its descendants (walls, links).
 *
 * @param string $id The ID of the side to delete.
 * @return bool True on success, false if not found.
 */
function delete_side($id) {
    $db = get_db();
    $initial_side_count = count($db['sides'] ?? []);

    // Find walls associated with the side
    $walls_to_delete_ids = [];
    foreach ($db['walls'] ?? [] as $wall) {
        if ($wall['side_id'] === $id) {
            $walls_to_delete_ids[] = $wall['id'];
        }
    }

    // Filter links that belong to the walls being deleted
    if (!empty($walls_to_delete_ids)) {
        $db['links'] = array_values(array_filter($db['links'] ?? [], function($link) use ($walls_to_delete_ids) {
            return !in_array($link['wall_id'], $walls_to_delete_ids);
        }));
    }

    // Filter walls that belong to the side being deleted
    $db['walls'] = array_values(array_filter($db['walls'] ?? [], function($wall) use ($id) {
        return $wall['side_id'] !== $id;
    }));

    // Filter the side itself
    $db['sides'] = array_values(array_filter($db['sides'] ?? [], function($side) use ($id) {
        return $side['id'] !== $id;
    }));

    if (count($db['sides'] ?? []) < $initial_side_count) {
        return save_db($db);
    }

    return false;
}


// --- Wall Functions ---

/**
 * Creates a new wall for a given side.
 *
 * @param string $side_id The ID of the parent side.
 * @param string $name The name of the new wall.
 * @return string|bool The new wall's ID on success, false on failure.
 */
function create_wall($side_id, $name) {
    $db = get_db();

    if (!get_side($side_id)) {
        error_log("Attempted to create wall for non-existent side ID: $side_id");
        return false;
    }

    $new_wall = [
        'id' => 'w_' . uniqid(),
        'side_id' => $side_id,
        'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'access_control' => [
            'type' => 'public', // 'public', 'password', 'codelist'
            'password' => ['hash' => null, 'salt' => null],
            'codelist' => []
        ]
    ];

    $db['walls'][] = $new_wall;

    if (save_db($db)) {
        return $new_wall['id'];
    }
    return false;
}

/**
 * Updates a wall's access control settings.
 *
 * @param string $id The ID of the wall to update.
 * @param string $type The new access type ('public', 'password', 'codelist').
 * @param mixed $value The corresponding value (password string or array of codes).
 * @return bool True on success, false on failure.
 */
function update_wall_access(string $id, string $type, $value = null): bool {
    $db = get_db();
    $found = false;
    foreach ($db['walls'] as &$wall) {
        if ($wall['id'] === $id) {
            // Reset access control to a clean state
            $wall['access_control'] = [
                'type' => 'public',
                'password' => ['hash' => null, 'salt' => null],
                'codelist' => []
            ];

            if ($type === 'password' && !empty($value)) {
                $password_data = hash_password($value);
                $wall['access_control']['type'] = 'password';
                $wall['access_control']['password']['hash'] = $password_data['hash'];
                $wall['access_control']['password']['salt'] = $password_data['salt'];
            } elseif ($type === 'codelist' && is_array($value) && !empty($value)) {
                $wall['access_control']['type'] = 'codelist';
                $hashed_codes = [];
                foreach ($value as $code) {
                    // Trim and ensure code is not empty
                    $trimmed_code = trim($code);
                    if (!empty($trimmed_code)) {
                        $hashed_codes[] = hash_password($trimmed_code);
                    }
                }
                $wall['access_control']['codelist'] = $hashed_codes;
            }

            $found = true;
            break;
        }
    }

    if ($found) {
        return save_db($db);
    }

    return false;
}

/**
 * Retrieves all walls for a specific side.
 *
 * @param string $side_id The ID of the side.
 * @return array A list of wall records.
 */
function get_walls_for_side($side_id) {
    $db = get_db();
    $walls_for_side = [];
    foreach ($db['walls'] ?? [] as $wall) {
        if ($wall['side_id'] === $side_id) {
            $walls_for_side[] = $wall;
        }
    }
    return $walls_for_side;
}

/**
 * Retrieves a single wall by its ID.
 *
 * @param string $id The ID of the wall.
 * @return array|null The wall data, or null if not found.
 */
function get_wall($id) {
    $db = get_db();
    foreach ($db['walls'] ?? [] as $wall) {
        if ($wall['id'] === $id) {
            return $wall;
        }
    }
    return null;
}

/**
 * Updates a wall's name.
 *
 * @param string $id The ID of the wall to update.
 * @param string $newName The new name.
 * @return bool True on success, false on failure.
 */
function update_wall($id, $newName) {
    $db = get_db();
    $found = false;
    foreach ($db['walls'] as &$wall) {
        if ($wall['id'] === $id) {
            $wall['name'] = htmlspecialchars($newName, ENT_QUOTES, 'UTF-8');
            $found = true;
            break;
        }
    }

    if ($found) {
        return save_db($db);
    }

    return false;
}

/**
 * Deletes a wall and all its links.
 *
 * @param string $id The ID of the wall to delete.
 * @return bool True on success, false if not found.
 */
function delete_wall($id) {
    $db = get_db();
    $initial_wall_count = count($db['walls'] ?? []);

    // Filter links that belong to the wall being deleted
    $db['links'] = array_values(array_filter($db['links'] ?? [], function($link) use ($id) {
        return $link['wall_id'] !== $id;
    }));

    // Filter the wall itself
    $db['walls'] = array_values(array_filter($db['walls'] ?? [], function($wall) use ($id) {
        return $wall['id'] !== $id;
    }));

    if (count($db['walls'] ?? []) < $initial_wall_count) {
        return save_db($db);
    }

    return false;
}


// --- Link Functions ---

/**
 * Creates a new link for a given wall.
 *
 * @param string $wall_id The ID of the parent wall.
 * @param string $title The title of the link.
 * @param string $url The URL of the link.
 * @param string $description (Optional) A description for the link.
 * @param string $image (Optional) An image URL for the link.
 * @return string|bool The new link's ID on success, false on failure.
 */
function create_link(string $wall_id, array $link_data): string|false {
    $db = get_db();

    if (!get_wall($wall_id)) {
        error_log("Attempted to create link for non-existent wall ID: $wall_id");
        return false;
    }

    $new_link = [
        'id' => 'l_' . uniqid(),
        'wall_id' => $wall_id,
        'title' => $link_data['title'],
        'url' => $link_data['url'],
        'description' => $link_data['description'],
        'image' => $link_data['image'],
    ];

    $db['links'][] = $new_link;

    if (save_db($db)) {
        return $new_link['id'];
    }
    return false;
}

/**
 * Retrieves all links for a specific wall.
 *
 * @param string $wall_id The ID of the wall.
 * @return array A list of link records.
 */
function get_links_for_wall($wall_id) {
    $db = get_db();
    $links_for_wall = [];
    foreach ($db['links'] ?? [] as $link) {
        if ($link['wall_id'] === $wall_id) {
            $links_for_wall[] = $link;
        }
    }
    return $links_for_wall;
}

/**
 * Retrieves a single link by its ID.
 *
 * @param string $id The ID of the link.
 * @return array|null The link data, or null if not found.
 */
function get_link($id) {
    $db = get_db();
    foreach ($db['links'] ?? [] as $link) {
        if ($link['id'] === $id) {
            return $link;
        }
    }
    return null;
}

/**
 * Updates a link's details.
 *
 * @param string $id The ID of the link to update.
 * @param string $title The new title.
 * @param string $url The new URL.
 * @param string $description The new description.
 * @param string $image The new image URL.
 * @return bool True on success, false on failure.
 */
function update_link(string $id, array $link_data): bool {
    $db = get_db();
    $found = false;

    foreach ($db['links'] as &$link) {
        if ($link['id'] === $id) {
            $link['title'] = $link_data['title'];
            $link['url'] = $link_data['url'];
            $link['description'] = $link_data['description'];
            // Only update image if it's provided, to not overwrite it with null
            if (isset($link_data['image'])) {
                $link['image'] = $link_data['image'];
            }
            $found = true;
            break;
        }
    }

    if ($found) {
        return save_db($db);
    }

    return false;
}

/**
 * Deletes a link by its ID.
 *
 * @param string $id The ID of the link to delete.
 * @return bool True on success, false if not found.
 */
function delete_link($id) {
    $db = get_db();
    $initial_link_count = count($db['links'] ?? []);

    $db['links'] = array_values(array_filter($db['links'] ?? [], function($link) use ($id) {
        return $link['id'] !== $id;
    }));

    if (count($db['links'] ?? []) < $initial_link_count) {
        return save_db($db);
    }

    return false;
}

?>
