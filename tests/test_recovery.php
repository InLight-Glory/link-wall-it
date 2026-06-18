<?php
require_once __DIR__ . '/../app/core/functions.php';
require_once __DIR__ . '/../app/core/mnemonic.php';

echo "Testing Recovery...\n";

// Generate phrase
$phrase = generate_recovery_phrase();
if (!$phrase) die("Failed to generate phrase\n");
// echo "Phrase: $phrase\n";

// Hash it
$recovery_data = hash_password($phrase);

// Inject into DB
$db = get_db();
// Remove if exists
$db['users'] = array_values(array_filter($db['users'] ?? [], function($u) { return $u['username'] !== 'recovery_user'; }));

$db['users'][] = [
    'username' => 'recovery_user',
    'password_hash' => 'old_hash',
    'salt' => 'old_salt',
    'recovery_hash' => $recovery_data['hash'],
    'recovery_salt' => $recovery_data['salt']
];
save_db($db);

// Verify phrase
$user = null;
foreach (get_db()['users'] as $u) {
    if ($u['username'] === 'recovery_user') $user = $u;
}

if (verify_password($phrase, $user['recovery_hash'], $user['recovery_salt'])) {
    echo "Verify Phrase: PASS\n";
} else {
    echo "Verify Phrase: FAIL\n";
}

// Reset Password Logic (simulated)
if (update_user_password('recovery_user', 'new_pass')) {
    echo "Reset Password: PASS\n";
} else {
    echo "Reset Password: FAIL\n";
}

// Verify login with new pass
if (login_user('recovery_user', 'new_pass')) {
    echo "Login with New Pass: PASS\n";
} else {
    echo "Login with New Pass: FAIL\n";
}

// Clean up
$db = get_db();
$db['users'] = array_values(array_filter($db['users'], function($u) { return $u['username'] !== 'recovery_user'; }));
save_db($db);
