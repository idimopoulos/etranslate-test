<?php

declare(strict_types=1);

/**
 * Escapes for HTML output.
 */
function e(mixed $value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Pretty-prints JSON when possible, otherwise returns the input.
 */
function pretty(?string $raw): string {
  if ($raw === NULL || $raw === '') {
    return '';
  }
  $decoded = json_decode($raw);
  return json_last_error() === JSON_ERROR_NONE
    ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : $raw;
}
