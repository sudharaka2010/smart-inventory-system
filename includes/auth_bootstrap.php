<?php
// includes/auth_bootstrap.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!headers_sent()) {
  header("X-Frame-Options: SAMEORIGIN");
  header("X-Content-Type-Options: nosniff");
  header("Referrer-Policy: strict-origin-when-cross-origin");
  header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob: https:; img-src 'self' data: https: blob:; frame-ancestors 'self';");
}
date_default_timezone_set('Asia/Colombo');

/** Require any logged-in user */
function require_login(): void {
  if (empty($_SESSION['username'])) {
    header("Location: /rbstorsg/auth/login.php");
    exit();
  }
}

/** Quick helpers */
function current_user(): array {
  return [
    'username' => $_SESSION['username'] ?? null,
    'role'     => $_SESSION['role'] ?? null,   // 'Admin' or 'Staff'
    'name'     => $_SESSION['name'] ?? ($_SESSION['username'] ?? 'User'),
  ];
}

function has_role(string $role): bool {
  return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/** Require one of the allowed roles */
function require_role(array $roles): void {
  if (empty($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) {
    http_response_code(403);
    echo "<!doctype html><meta charset='utf-8'><h1>403 Forbidden</h1><p>Insufficient role.</p>";
    exit();
  }
}
