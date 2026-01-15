<?php
// This script updates the .env file to include Paystack test keys

// Define the Paystack test keys
$secretKey = 'sk_test_b5519f8d95c0876444d91c8e303d889bcc062f05';
$publicKey = 'pk_test_c2a59209a890eb5ce4fc5a412f2d90c8ccf515a1';

// Path to your .env file
$envFile = __DIR__ . '/.env';

if (\!file_exists($envFile)) {
    echo "Error: .env file not found\n";
    exit(1);
}

// Read the current .env content
$envContent = file_get_contents($envFile);

// Function to update an env variable
function updateEnvVariable($content, $key, $value) {
    // Check if the key already exists
    if (preg_match("/^{$key}=/m", $content)) {
        // Replace existing value
        return preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
    } else {
        // Add new variable
        return $content . PHP_EOL . "{$key}={$value}";
    }
}

// Update the Paystack keys
$envContent = updateEnvVariable($envContent, 'PAYSTACK_SECRET_KEY', $secretKey);
$envContent = updateEnvVariable($envContent, 'PAYSTACK_PUBLIC_KEY', $publicKey);

// Save the updated content back to the .env file
if (file_put_contents($envFile, $envContent)) {
    echo "Paystack test keys have been successfully added to your .env file\n";
} else {
    echo "Error: Failed to update .env file\n";
    exit(1);
}

echo "Done\!\n";
