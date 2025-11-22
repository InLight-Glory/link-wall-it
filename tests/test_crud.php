<?php
require_once __DIR__ . '/../app/core/functions.php';

function assert_true($condition, $message) {
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        echo "[FAIL] $message\n";
        exit(1);
    }
}

echo "Testing CRUD Operations...\n";

// 1. Building CRUD
echo "\n--- Building CRUD ---\n";
$b_name = "Test Building " . uniqid();
$b_id = create_building($b_name);
assert_true($b_id !== false, "Create Building");

$b = get_building($b_id);
assert_true($b['name'] === $b_name, "Get Building");

$new_b_name = $b_name . " Updated";
assert_true(update_building($b_id, $new_b_name), "Update Building");
$b = get_building($b_id);
assert_true($b['name'] === $new_b_name, "Verify Building Update");

// 2. Side CRUD
echo "\n--- Side CRUD ---\n";
$s_name = "Test Side";
$s_id = create_side($b_id, $s_name);
assert_true($s_id !== false, "Create Side");

$s = get_side($s_id);
assert_true($s['name'] === $s_name, "Get Side");
assert_true($s['building_id'] === $b_id, "Side Parent Check");

$new_s_name = $s_name . " Updated";
assert_true(update_side($s_id, $new_s_name), "Update Side");
$s = get_side($s_id);
assert_true($s['name'] === $new_s_name, "Verify Side Update");

// 3. Wall CRUD
echo "\n--- Wall CRUD ---\n";
$w_name = "Test Wall";
$w_id = create_wall($s_id, $w_name);
assert_true($w_id !== false, "Create Wall");

$w = get_wall($w_id);
assert_true($w['name'] === $w_name, "Get Wall");
assert_true($w['side_id'] === $s_id, "Wall Parent Check");

$new_w_name = $w_name . " Updated";
assert_true(update_wall($w_id, $new_w_name), "Update Wall");
$w = get_wall($w_id);
assert_true($w['name'] === $new_w_name, "Verify Wall Update");

// 4. Link CRUD
echo "\n--- Link CRUD ---\n";
$l_data = [
    'title' => 'Test Link',
    'url' => 'https://example.com',
    'description' => 'Desc',
    'image' => null
];
$l_id = create_link($w_id, $l_data);
assert_true($l_id !== false, "Create Link");

$l = get_link($l_id);
assert_true($l['title'] === 'Test Link', "Get Link");
assert_true($l['wall_id'] === $w_id, "Link Parent Check");

$l_data['title'] = 'Test Link Updated';
assert_true(update_link($l_id, $l_data), "Update Link");
$l = get_link($l_id);
assert_true($l['title'] === 'Test Link Updated', "Verify Link Update");

// 5. Deletion (Bottom up)
echo "\n--- Deletion ---\n";
assert_true(delete_link($l_id), "Delete Link");
assert_true(get_link($l_id) === null, "Verify Link Deleted");

// Test cascading delete
// Create a link again to test cascade
$l_id_2 = create_link($w_id, $l_data);

assert_true(delete_wall($w_id), "Delete Wall");
assert_true(get_wall($w_id) === null, "Verify Wall Deleted");
assert_true(get_link($l_id_2) === null, "Verify Link Cascade Deleted");

// Cascade from Side
$w_id_2 = create_wall($s_id, "Wall 2");
assert_true(delete_side($s_id), "Delete Side");
assert_true(get_side($s_id) === null, "Verify Side Deleted");
assert_true(get_wall($w_id_2) === null, "Verify Wall Cascade Deleted");

// Cascade from Building
$s_id_2 = create_side($b_id, "Side 2");
assert_true(delete_building($b_id), "Delete Building");
assert_true(get_building($b_id) === null, "Verify Building Deleted");
assert_true(get_side($s_id_2) === null, "Verify Side Cascade Deleted");

echo "\nAll CRUD Tests Passed!\n";
