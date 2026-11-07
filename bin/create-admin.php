<?php
// Creates an account (or finds the existing one) and gives it the admin role.
//   php bin/create-admin.php you@example.com "a-strong-password" "Your Name"
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit('Run this from the command line.');
require dirname(__DIR__) . '/src/bootstrap.php';

use McRelish\Core\{Database, Uuid};

[$script, $email, $password] = array_pad($argv, 3, '');
$name = $argv[3] ?? '';
$email = strtolower(trim((string) $email));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) exit("Usage: php bin/create-admin.php email password [full name]\n");

$user = Database::one('SELECT id FROM users WHERE email = ?', [$email]);
if (!$user) {
    if (strlen($password) < 8 || strlen($password) > 72) exit("Choose a password of 8 to 72 characters.\n");
    $id = Uuid::v4();
    Database::run('INSERT INTO users (id, email, password_hash, full_name) VALUES (?, ?, ?, ?)', [$id, $email, password_hash($password, PASSWORD_DEFAULT), $name]);
    echo "Created account $email.\n";
} else {
    $id = $user['id'];
    if ($password !== '') {
        if (strlen($password) < 8 || strlen($password) > 72) exit("Choose a password of 8 to 72 characters.\n");
        Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
        echo "Updated the password.\n";
    }
}
Database::run('INSERT IGNORE INTO user_roles (user_id, role) VALUES (?, \'admin\')', [$id]);
echo "$email is now an admin. Sign in on the website, then open /admin.\n";
