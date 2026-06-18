<?php
require_once __DIR__ . '/../app/core/functions.php';

echo "Testing Settings Update...\n";

// Mock saving settings
$db = get_db();
$original_settings = $db['settings'];

$new_settings = [
    'site_title' => 'Test Title',
    'stripe_publishable_key' => 'pk_test_123',
];
$db['settings'] = array_merge($db['settings'], $new_settings);
if (!save_db($db)) {
    echo "Failed to save db\n";
    exit(1);
}

$reloaded_db = get_db();
if ($reloaded_db['settings']['site_title'] === 'Test Title' && $reloaded_db['settings']['stripe_publishable_key'] === 'pk_test_123') {
    echo "Settings Update: PASS\n";
} else {
    echo "Settings Update: FAIL\n";
    print_r($reloaded_db['settings']);
}

// Restore
$db['settings'] = $original_settings;
save_db($db);
