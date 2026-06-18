<?php
require_once __DIR__ . '/../app/core/functions.php';

echo "Testing Auth...\n";

// Test correct login
if (login_user('admin', 'admin')) {
    echo "Login success: PASS\n";

    // Test Change Password
    if (update_user_password('admin', 'newpass')) {
        echo "Update Password: PASS\n";
    } else {
        echo "Update Password: FAIL\n";
    }

    // Verify new login
    if (login_user('admin', 'newpass')) {
        echo "Login with new pass: PASS\n";
    } else {
        echo "Login with new pass: FAIL\n";
    }

    // Revert password for other tests
    update_user_password('admin', 'admin');

} else {
    echo "Login success: FAIL\n";
    // Debug: check if user exists
    $db = get_db();
    print_r($db['users']);
}

// Test incorrect login
logout_user();
if (!login_user('admin', 'wrong')) {
    echo "Login failure (wrong pass): PASS\n";
} else {
    echo "Login failure (wrong pass): FAIL\n";
}

// Test logged in check
login_user('admin', 'admin');
if (is_logged_in()) {
    echo "Is Logged In: PASS\n";
} else {
    echo "Is Logged In: FAIL\n";
}

logout_user();
if (!is_logged_in()) {
    echo "Logout: PASS\n";
} else {
    echo "Logout: FAIL\n";
}
