<?php
// includes/permissions.php

$ROLE_PERMS = [
  'Admin' => [
    'dashboard.view',
    'inventory.view','inventory.edit','inventory.delete',
    'billing.view','billing.edit','billing.delete',
    'transport.view','transport.edit','transport.delete',
    'vehicle.view','vehicle.edit','vehicle.delete',
    'user.view','user.edit','user.delete',
    'reports.view',
  ],
  'Staff' => [
    'dashboard.view',
    'inventory.view','inventory.edit',
    'billing.view','billing.edit',
    'transport.view','transport.edit',
    'vehicle.view',
    // no deletes, no users, no reports by default
  ],
];

function can(string $perm): bool {
  global $ROLE_PERMS;
  $role = $_SESSION['role'] ?? null;
  if (!$role || !isset($ROLE_PERMS[$role])) return false;
  return in_array($perm, $ROLE_PERMS[$role], true);
}

function require_perm(string $perm): void {
  if (!can($perm)) {
    http_response_code(403);
    echo "<!doctype html><meta charset='utf-8'><h1>403 Forbidden</h1><p>You don’t have permission to access this.</p>";
    exit();
  }
}

/** Optional: show element only if permitted */
function if_can(string $perm, callable $cb): void {
  if (can($perm)) { $cb(); }
}
