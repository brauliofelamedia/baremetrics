<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ROLES ===" . PHP_EOL;
$roles = \Spatie\Permission\Models\Role::all();
foreach ($roles as $role) {
    echo "ID: {$role->id}, Name: {$role->name}" . PHP_EOL;
}

echo PHP_EOL . "=== USERS WITH ROLES ===" . PHP_EOL;
$users = \App\Models\User::with('roles')->get();
foreach ($users as $user) {
    $roleNames = $user->roles->pluck('name')->join(', ');
    echo "User: {$user->name} ({$user->email}) - Roles: {$roleNames}" . PHP_EOL;
}
