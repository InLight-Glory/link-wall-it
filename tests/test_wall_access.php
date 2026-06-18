<?php
require_once __DIR__ . '/../app/core/functions.php';

echo "Testing Wall Payment Access...\n";

// Need to mock admin session?
// create_building etc don't check auth internally, only the admin pages do.
// So we can call functions directly.

$building_id = create_building('Test Building');
if (!$building_id) exit("Failed to create building\n");

$side_id = create_side($building_id, 'Test Side');
if (!$side_id) exit("Failed to create side\n");

$wall_id = create_wall($side_id, 'Test Wall');
if (!$wall_id) exit("Failed to create wall\n");

// Update to Payment
if (update_wall_access($wall_id, 'payment', 9.99)) {
    echo "Update Wall Access: PASS\n";
} else {
    echo "Update Wall Access: FAIL\n";
    exit(1);
}

$wall = get_wall($wall_id);
if ($wall['access_control']['type'] === 'payment' && abs($wall['access_control']['payment']['price'] - 9.99) < 0.001) {
    echo "Verify Price (9.99): PASS\n";
} else {
    echo "Verify Price: FAIL\n";
    print_r($wall['access_control']);
}

// Check USD default
if ($wall['access_control']['payment']['currency'] === 'USD') {
    echo "Verify Currency: PASS\n";
} else {
    echo "Verify Currency: FAIL\n";
}
