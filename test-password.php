<?php
$hash = '$2y$10$bZyMwQj1wyc01d5g0K2w8.mKzHK54bux4gQqC27BVuk2pgBVysGoO';
$password = 'password123';

if (password_verify($password, $hash)) {
    echo "✅ Password matches!";
} else {
    echo "❌ Password does NOT match!";
}
?>
