<?php

/**
 * Returns the current site theme: 'default' | 'dark' | 'evening'.
 * Looked up from settings.theme in database.json; falls back to 'default' if unset/invalid.
 */
function current_theme(): string {
    static $cached = null;
    if ($cached !== null) { return $cached; }

    $allowed = ['default', 'dark', 'evening'];

    // 1) Visitor cookie wins — once a guest picks a theme it sticks regardless of site default.
    if (!empty($_COOKIE['lwi_theme']) && in_array($_COOKIE['lwi_theme'], $allowed, true)) {
        $cached = $_COOKIE['lwi_theme'];
        return $cached;
    }

    // 2) Fall back to admin's site-wide default in database.json.
    // Guarded with function_exists in case we're called before db.php loads.
    if (!function_exists('get_db')) { return 'default'; }
    $db = get_db();
    $t = $db['settings']['theme'] ?? 'default';
    $cached = in_array($t, $allowed, true) ? $t : 'default';
    return $cached;
}

/**
 * Emits the `data-theme="..."` attribute string for the <html> element.
 * Returns empty string for the default theme (no attribute = no override needed).
 */
function theme_html_attr(): string {
    $t = current_theme();
    if ($t === 'default') { return ''; }
    return ' data-theme="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '"';
}

/**
 * Emits a self-contained <script> tag that injects the floating theme picker
 * (top-right palette button) into the body and wires up cookie persistence.
 * Call once per page, anywhere before </body>.
 */
function theme_picker_html(): string {
    ob_start();
    ?>
<script>
(function () {
    var ROOT = document.documentElement;
    var THEMES = ['default', 'dark', 'evening'];

    function getCookie(name) {
        var m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return m ? decodeURIComponent(m[2]) : null;
    }
    function setCookie(name, value) {
        var oneYear = 60 * 60 * 24 * 365;
        document.cookie = name + '=' + encodeURIComponent(value)
            + '; path=/; max-age=' + oneYear + '; samesite=lax';
    }
    function applyTheme(t) {
        if (t === 'default') ROOT.removeAttribute('data-theme');
        else ROOT.setAttribute('data-theme', t);
    }

    function build() {
        var existing = document.querySelector('[data-theme-picker]');
        if (existing) return existing;

        var pick = document.createElement('div');
        pick.className = 'theme-picker';
        pick.setAttribute('data-theme-picker', '');

        pick.innerHTML = ''
            + '<button type="button" class="theme-picker__button" aria-label="Change theme" aria-haspopup="true" aria-expanded="false">'
            +   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            +     '<circle cx="12" cy="12" r="9"/>'
            +     '<circle cx="7.5" cy="10" r="1.2" fill="currentColor" stroke="none"/>'
            +     '<circle cx="12" cy="7.5" r="1.2" fill="currentColor" stroke="none"/>'
            +     '<circle cx="16.5" cy="10" r="1.2" fill="currentColor" stroke="none"/>'
            +     '<circle cx="15" cy="14.5" r="1.2" fill="currentColor" stroke="none"/>'
            +     '<path d="M12 21a3 3 0 0 0 3-3c0-1-.5-1.5-1.5-2.2-1-.7-1.5-1.3-1.5-2.3a2.5 2.5 0 0 1 2.5-2.5h.5"/>'
            +   '</svg>'
            + '</button>'
            + '<div class="theme-picker__menu" hidden role="menu">'
            +   themeOptionHtml('default', 'Default', '#fafafa', '#1f2937')
            +   themeOptionHtml('dark',    'Dark',    '#0a0e1a', '#60a5fa')
            +   themeOptionHtml('evening', 'Evening', '#f7ecd4', '#8b4513')
            + '</div>';

        document.body.appendChild(pick);
        return pick;
    }

    function themeOptionHtml(key, label, swatchBg, swatchAccent) {
        return '<button type="button" class="theme-picker__option" data-theme-set="' + key + '" role="menuitem">'
            +   '<span class="theme-picker__swatch" style="background:' + swatchBg + ';">'
            +     '<span class="theme-picker__swatch-dot" style="background:' + swatchAccent + ';"></span>'
            +   '</span>'
            +   '<span class="theme-picker__label">' + label + '</span>'
            +   '<span class="theme-picker__check" aria-hidden="true">&#10003;</span>'
            + '</button>';
    }

    function markCurrent(picker) {
        var current = ROOT.getAttribute('data-theme') || 'default';
        picker.querySelectorAll('[data-theme-set]').forEach(function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-theme-set') === current);
        });
    }

    function init() {
        // Belt-and-suspenders: re-apply cookie theme client-side in case the page was
        // server-rendered without it (e.g., cached HTML).
        var saved = getCookie('lwi_theme');
        if (saved && THEMES.indexOf(saved) !== -1) applyTheme(saved);

        var picker = build();
        var button = picker.querySelector('.theme-picker__button');
        var menu = picker.querySelector('.theme-picker__menu');
        markCurrent(picker);

        button.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = !menu.hidden;
            menu.hidden = open;
            button.setAttribute('aria-expanded', String(!open));
        });
        document.addEventListener('click', function (e) {
            if (!picker.contains(e.target)) {
                menu.hidden = true;
                button.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                menu.hidden = true;
                button.setAttribute('aria-expanded', 'false');
            }
        });
        picker.querySelectorAll('[data-theme-set]').forEach(function (opt) {
            opt.addEventListener('click', function () {
                var val = opt.getAttribute('data-theme-set');
                applyTheme(val);
                setCookie('lwi_theme', val);
                markCurrent(picker);
                menu.hidden = true;
                button.setAttribute('aria-expanded', 'false');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
    <?php
    return ob_get_clean();
}

// Require helper files
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/encryption.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/install_secret.php';
require_once __DIR__ . '/email_access.php';
require_once __DIR__ . '/invites.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/payment_stripe.php';
require_once __DIR__ . '/payment_paypal.php';
require_once __DIR__ . '/slugs.php';

// Check installation status
$lock_file = __DIR__ . '/../../data/installed.lock';
if (!file_exists($lock_file) && php_sapi_name() !== 'cli') {
    // Determine if we are already on the install page to avoid infinite redirect
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    if (substr($script_name, -11) !== 'install.php') {
        // Handle redirection for subdirectories (e.g. admin/)
        $redirect_url = 'install.php';
        if (strpos($script_name, '/admin/') !== false) {
            $redirect_url = '../install.php';
        }

        header('Location: ' . $redirect_url);
        exit;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Auth Functions ---

/**
 * Logs in a user.
 *
 * @param string $username
 * @param string $password
 * @return bool True on success, false on failure.
 */
function login_user($username, $password) {
    $db = get_db();
    $users = $db['users'] ?? [];

    foreach ($users as $user) {
        if ($user['username'] === $username) {
            // Support a temporary plaintext marker for manual resets: 'PLAINTEXT:<password>'
            if (isset($user['password_hash']) && strpos($user['password_hash'], 'PLAINTEXT:') === 0) {
                $plain = substr($user['password_hash'], strlen('PLAINTEXT:'));
                if ($password === $plain) {
                    $_SESSION['user_id'] = $user['username']; // Simple session user ID
                    return true;
                }
                return false;
            }

            // Verify password using stored hash and salt
            $salt = $user['salt'] ?? '';
            if (verify_password($password, $user['password_hash'], $salt)) {
                $_SESSION['user_id'] = $user['username']; // Simple session user ID
                return true;
            }
        }
    }
    return false;
}

/**
 * Checks if a user is logged in.
 *
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Logs out the current user.
 */
function logout_user() {
    unset($_SESSION['user_id']);
    session_destroy();
}

/**
 * Requires a user to be logged in. If not, redirects to login page.
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Updates a user's password.
 *
 * @param string $username The username of the user.
 * @param string $new_password The new password.
 * @return bool True on success, false on failure.
 */
function update_user_password($username, $new_password) {
    $db = get_db();
    $found = false;
    foreach ($db['users'] as &$user) {
        if ($user['username'] === $username) {
            $password_data = hash_password($new_password);
            $user['password_hash'] = $password_data['hash'];
            $user['salt'] = $password_data['salt'];
            $found = true;
            break;
        }
    }

    if ($found) {
        return save_db($db);
    }
    return false;
}

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

/* ------------------------------------------------------------------ */
/* Public visibility filters (Phase 4.1 — "don't advertise private content")
 *
 * A Wall is publicly listable iff its access_control.type is 'public'.
 * A Side / Building is publicly listable iff it contains at least one publicly-
 * listable descendant. Direct URLs to private walls still work — the locked
 * gate on wall.php still handles them. These filters are only for *listings*.
 * ------------------------------------------------------------------ */

function wall_is_publicly_listable(array $wall): bool {
    return ($wall['access_control']['type'] ?? 'public') === 'public';
}

function side_is_publicly_listable(string $side_id): bool {
    foreach (get_walls_for_side($side_id) as $w) {
        if (wall_is_publicly_listable($w)) { return true; }
    }
    return false;
}

function building_is_publicly_listable(string $building_id): bool {
    foreach (get_sides_for_building($building_id) as $s) {
        if (side_is_publicly_listable($s['id'])) { return true; }
    }
    return false;
}

function get_publicly_listable_buildings(): array {
    return array_values(array_filter(
        get_all_buildings(),
        fn($b) => building_is_publicly_listable($b['id'])
    ));
}

function get_publicly_listable_sides_for_building(string $building_id): array {
    return array_values(array_filter(
        get_sides_for_building($building_id),
        fn($s) => side_is_publicly_listable($s['id'])
    ));
}

function get_publicly_listable_walls_for_side(string $side_id): array {
    return array_values(array_filter(
        get_walls_for_side($side_id),
        'wall_is_publicly_listable'
    ));
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
        'slug' => generate_unique_slug() ?? '',
        'access_control' => [
            'type' => 'public', // 'public' | 'password' | 'codelist' | 'payment' | 'email_allowlist'
            'password' => ['hash' => null, 'salt' => null],
            'codelist' => [],
            'payment' => ['price' => 0, 'currency' => 'USD'],
            'email_allowlist' => [],
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
            // Phase 3: preserve email_allowlist + (creator-curated) configurations across
            // type changes. Switching from 'email_allowlist' to 'public' should not wipe
            // the carefully-built email list.
            $existing_allowlist = $wall['access_control']['email_allowlist'] ?? [];
            $existing_payment = $wall['access_control']['payment'] ?? ['price' => 0, 'currency' => 'USD'];

            $wall['access_control'] = [
                'type' => 'public',
                'password' => ['hash' => null, 'salt' => null],
                'codelist' => [],
                'payment' => $existing_payment,
                'email_allowlist' => $existing_allowlist,
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
                    $trimmed_code = trim($code);
                    if (!empty($trimmed_code)) {
                        $hashed_codes[] = hash_password($trimmed_code);
                    }
                }
                $wall['access_control']['codelist'] = $hashed_codes;
            } elseif ($type === 'payment') {
                $wall['access_control']['type'] = 'payment';
                // Phase 4: $value may be a numeric price (legacy) OR an array with
                // ['price' => float, 'providers' => ['stripe', 'paypal']] (new).
                if (is_array($value)) {
                    $wall['access_control']['payment']['price'] = (float)($value['price'] ?? 0);
                    $providers = $value['providers'] ?? [];
                    $providers = array_values(array_intersect(
                        is_array($providers) ? $providers : [],
                        ['stripe', 'paypal']
                    ));
                    $wall['access_control']['payment']['providers'] = $providers;
                } else {
                    $wall['access_control']['payment']['price'] = (float)$value;
                }
                $wall['access_control']['payment']['currency'] = 'USD';
            } elseif ($type === 'email_allowlist') {
                // The allowlist itself is managed via add_wall_email / remove_wall_email
                // separately. Switching to this type just flips the gate.
                $wall['access_control']['type'] = 'email_allowlist';
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
function create_link(string $wall_id, array $link_data) {
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
 * Moves a set of links to a different wall (mutates wall_id in place).
 * Returns the number of links actually moved (silently ignores unknown ids).
 *
 * Note: title/description ciphertext is NOT re-encrypted. If the source wall was
 * password-protected, the moved link's stored text remains encrypted with the
 * SOURCE password — it won't decrypt on the target. Caller should warn admins.
 */
function move_links_to_wall(array $link_ids, string $target_wall_id): int {
    if (empty($link_ids)) { return 0; }
    if (!get_wall($target_wall_id)) { return 0; }

    $db = get_db();
    $moved = 0;
    foreach ($db['links'] as &$link) {
        if (in_array($link['id'], $link_ids, true)) {
            if ($link['wall_id'] !== $target_wall_id) {
                $link['wall_id'] = $target_wall_id;
                $moved++;
            }
        }
    }
    unset($link);
    if ($moved > 0) { save_db($db); }
    return $moved;
}

/**
 * Duplicates a set of links onto a target wall. Each duplicate gets a fresh `l_*` id
 * and keeps the same title/url/description/image as the original. Returns the count
 * of links actually duplicated.
 *
 * Same encryption caveat as move_links_to_wall — ciphertext is copied verbatim.
 *
 * The duplicates point at the SAME image file path as the originals; we don't copy
 * the underlying file. If you later need to deduplicate images, do it as a separate
 * pass.
 */
function duplicate_links_to_wall(array $link_ids, string $target_wall_id): int {
    if (empty($link_ids)) { return 0; }
    if (!get_wall($target_wall_id)) { return 0; }

    $db = get_db();
    $original_count = count($db['links']);
    $duplicated = 0;

    // Iterate by index so the new copies we append don't get re-duplicated themselves.
    for ($i = 0; $i < $original_count; $i++) {
        if (!in_array($db['links'][$i]['id'], $link_ids, true)) { continue; }
        $copy = $db['links'][$i];
        $copy['id'] = 'l_' . uniqid('', true);
        $copy['wall_id'] = $target_wall_id;
        $db['links'][] = $copy;
        $duplicated++;
    }
    if ($duplicated > 0) { save_db($db); }
    return $duplicated;
}

/**
 * Reorders the links belonging to a wall to match the given array of link IDs.
 * Any link IDs in the input that don't belong to the wall are ignored. Any links
 * on the wall NOT in the input are appended at the end (preserves any link the
 * caller didn't know about).
 *
 * @param string $wall_id The wall whose links to reorder.
 * @param array  $ordered_link_ids Array of link IDs in the desired new order.
 * @return bool True on save, false otherwise.
 */
function reorder_links_for_wall(string $wall_id, array $ordered_link_ids): bool {
    $db = get_db();
    if (empty($db['links'])) { return false; }

    // Partition: links on this wall vs others.
    $on_wall = [];
    $others  = [];
    foreach ($db['links'] as $link) {
        if (($link['wall_id'] ?? '') === $wall_id) {
            $on_wall[$link['id']] = $link;
        } else {
            $others[] = $link;
        }
    }
    if (empty($on_wall)) { return false; }

    // Apply requested order; drop unknown ids; append any remaining (not-mentioned) at end.
    $new_on_wall = [];
    foreach ($ordered_link_ids as $id) {
        if (isset($on_wall[$id])) {
            $new_on_wall[] = $on_wall[$id];
            unset($on_wall[$id]);
        }
    }
    foreach ($on_wall as $remaining) {
        $new_on_wall[] = $remaining;
    }

    $db['links'] = array_merge($others, $new_on_wall);
    return save_db($db);
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
