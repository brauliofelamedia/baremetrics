<?php

require_once __DIR__ . '/vendor/autoload.php';

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CancellationToken;
use Illuminate\Support\Str;
use Carbon\Carbon;

echo "=== Testing Cancellation Token Verification ===\n\n";

// Create a test token for melortegag@gmail.com
$email = 'melortegag@gmail.com';
$token = Str::random(64);

echo "Creating test token for email: $email\n";
echo "Token: $token\n\n";

$tokenRecord = CancellationToken::create([
    'email' => $email,
    'token' => $token,
    'expires_at' => Carbon::now()->addHours(24),
    'is_used' => false,
]);

echo "Token created successfully!\n";
echo "Token ID: " . $tokenRecord->id . "\n";
echo "Expires at: " . $tokenRecord->expires_at . "\n\n";

echo "Test URL: https://baremetrics.local/gohighlevel/cancellation/verify?token=$token\n\n";

echo "=== Test Setup Complete ===\n";