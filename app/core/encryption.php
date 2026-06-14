<?php

/**
 * Encryption helpers — password hashing, AES-256-GCM, code-list verification.
 *
 * APP_SECRET_KEY is loaded from data/app_secret.key (gitignored).
 * On first run the legacy default is written there so existing encrypted data
 * stays readable.  New installs should replace the key file content with a
 * unique 64-hex-char secret BEFORE creating any encrypted walls.
 */

// --- APP_SECRET_KEY bootstrap ------------------------------------------------
$_app_secret_file = __DIR__ . '/../../data/app_secret.key';
$_app_secret_legacy = 'e9a3f2c8b1d4e7f6a3b2c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0';

if (file_exists($_app_secret_file)) {
    $_app_secret_raw = trim(file_get_contents($_app_secret_file));
    if (preg_match('/^[a-f0-9]{64}$/i', $_app_secret_raw)) {
        define('APP_SECRET_KEY', $_app_secret_raw);
    } else {
        error_log('[Engine:encryption] Malformed app_secret.key — falling back to legacy key.');
        define('APP_SECRET_KEY', $_app_secret_legacy);
    }
} else {
    // First run: persist the legacy key so the file exists going forward.
    // Operators should replace this with a unique value for new installs.
    define('APP_SECRET_KEY', $_app_secret_legacy);
    @file_put_contents($_app_secret_file, $_app_secret_legacy . "\n", LOCK_EX);
    @chmod($_app_secret_file, 0600);
}
unset($_app_secret_file, $_app_secret_raw, $_app_secret_legacy);

/**
 * Hashes a password using a strong algorithm.
 *
 * @param string $password The plaintext password.
 * @return array An associative array containing the hash and the salt.
 */
function hash_password(string $password): array {
    $salt = random_bytes(16);
    // We add the salt to the password before hashing to further mitigate rainbow table attacks,
    // even though password_hash with a modern algo handles salting internally. This is a choice.
    $hash = password_hash($password . bin2hex($salt), PASSWORD_ARGON2ID);
    return [
        'hash' => $hash,
        'salt' => bin2hex($salt)
    ];
}

/**
 * Verifies a password against a stored hash and salt.
 *
 * @param string $password The plaintext password.
 * @param string $hash The stored hash.
 * @param string $saltHex The stored salt as a hex string.
 * @return bool True if the password is correct, false otherwise.
 */
function verify_password(string $password, string $hash, string $saltHex): bool {
    return password_verify($password . $saltHex, $hash);
}

/**
 * Derives a stable encryption key from a password and the app's secret key.
 *
 * @param string $password The password for the specific wall.
 * @return string The derived encryption key (32 bytes).
 */
function derive_encryption_key(string $password): string {
    $salt = APP_SECRET_KEY; // Using the app secret as the main salt for key derivation.
    $iterations = 100000; // A standard iteration count for PBKDF2.
    return hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
}

/**
 * Encrypts data using AES-256-GCM.
 *
 * @param string $data The plaintext data to encrypt.
 * @param string $password The password to derive the key from.
 * @return string|false The base64-encoded encrypted data (iv.tag.ciphertext), or false on failure.
 */
function encrypt_data(string $data, string $password) {
    $key = derive_encryption_key($password);
    $iv_length = openssl_cipher_iv_length('aes-256-gcm');
    $iv = random_bytes($iv_length);
    $tag = ''; // Will be filled by openssl_encrypt

    $ciphertext = openssl_encrypt(
        $data,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '', // AAD (Additional Associated Data)
        16  // The length of the authentication tag.
    );

    if ($ciphertext === false) {
        return false;
    }

    // Return iv, tag, and ciphertext concatenated and base64 encoded for easy storage.
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypts data encrypted with AES-256-GCM.
 *
 * @param string $encrypted_data The base64-encoded encrypted data.
 * @param string $password The password to derive the key from.
 * @return string|false The decrypted plaintext data, or false on failure (if tampering is detected or key is wrong).
 */
function decrypt_data(string $encrypted_data, string $password) {
    $key = derive_encryption_key($password);
    $decoded_data = base64_decode($encrypted_data, true);
    if ($decoded_data === false) {
        return false;
    }

    $iv_length = openssl_cipher_iv_length('aes-256-gcm');
    $tag_length = 16;

    // Ensure we have enough data for all parts
    if (strlen($decoded_data) < $iv_length + $tag_length) {
        return false;
    }

    // Extract parts
    $iv = substr($decoded_data, 0, $iv_length);
    $tag = substr($decoded_data, $iv_length, $tag_length);
    $ciphertext = substr($decoded_data, $iv_length + $tag_length);

    $decrypted_data = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    return $decrypted_data;
}

/**
 * Verifies a plaintext code against a list of hashed codes.
 *
 * @param string $plaintext_code The code to check.
 * @param array $hashed_codes_list The list of hashed code objects (each with 'hash' and 'salt').
 * @return bool True if a match is found, false otherwise.
 */
function verify_codelist_code(string $plaintext_code, array $hashed_codes_list): bool {
    foreach ($hashed_codes_list as $hashed_code) {
        if (verify_password($plaintext_code, $hashed_code['hash'], $hashed_code['salt'])) {
            return true;
        }
    }
    return false;
}
