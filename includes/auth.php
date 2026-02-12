<?php
// includes/auth.php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function auth_is_logged_in(): bool {
  return !empty($_SESSION['auth']['id']);
}

function auth_user_id(): ?int {
  return auth_is_logged_in() ? (int)$_SESSION['auth']['id'] : null;
}

function auth_role(): ?string {
  return auth_is_logged_in() ? (string)($_SESSION['auth']['role'] ?? null) : null;
}

function auth_name(): ?string {
  return auth_is_logged_in() ? (string)($_SESSION['auth']['name'] ?? null) : null;
}

function auth_email(): ?string {
  return auth_is_logged_in() ? (string)($_SESSION['auth']['email'] ?? null) : null;
}

// For clinic admins: which clinic they belong to
function auth_clinic_id(): ?int {
  return auth_is_logged_in() ? (int)($_SESSION['auth']['clinic_id'] ?? 0) : null;
}

function auth_set(int $id, string $role, string $name, string $email, ?int $clinicId = null): void {
  $_SESSION['auth'] = [
    'id' => $id,
    'role' => $role,
    'name' => $name,
    'email' => $email,
    'clinic_id' => $clinicId,
  ];
}

function auth_logout(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
  }
  session_destroy();
}

// --- Flash helpers ---
function flash_set(string $key, string $message): void {
  $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string {
  if (!isset($_SESSION['flash'][$key])) return null;
  $msg = (string)$_SESSION['flash'][$key];
  unset($_SESSION['flash'][$key]);
  return $msg;
}
function flash_clear(?string $key = null): void {
  if (!isset($_SESSION['flash'])) return;

  if ($key === null) {
    unset($_SESSION['flash']); // clear all flash messages
    return;
  }

  unset($_SESSION['flash'][$key]); // clear one key
  if (empty($_SESSION['flash'])) unset($_SESSION['flash']);
}


// --- Guards ---
function auth_require_login(string $baseUrl = '/AKAS', string $loginPath = '/pages/login.php'): void {
  if (!auth_is_logged_in()) {
    header('Location: ' . $baseUrl . $loginPath);
    exit;
  }
}


function auth_require_role(string $role, string $baseUrl = '/AKAS', string $loginPath = '/pages/login.php'): void {
  auth_require_login($baseUrl, $loginPath);

  if (auth_role() !== $role) {
    header('Location: ' . $baseUrl . '/index.php#top');
    exit;
  }
}


// Admin should ONLY see /admin/*.
function auth_enforce_admin_dashboard_only(string $baseUrl = '/AKAS'): void {
  if (!auth_is_logged_in()) return;
  if (auth_role() !== 'clinic_admin') return;

  $uri = $_SERVER['REQUEST_URI'] ?? '';

  // Allow: /admin/* and logout
  if (str_contains($uri, $baseUrl . '/admin/') || str_contains($uri, $baseUrl . '/logout.php')) {
    return;
  }

  header('Location: ' . $baseUrl . '/admin/dashboard.php');
  exit;
}
