<?php

declare(strict_types=1);

namespace App;

/**
 * Minimal PHP-template renderer.
 */
final class View {

  public static function render(string $template, array $vars = [], int $status = 200): void {
    http_response_code($status);
    extract($vars);
    ob_start();
    require __DIR__ . "/../templates/{$template}.php";
    $content = ob_get_clean();
    require __DIR__ . '/../templates/layout.php';
  }

  public static function redirect(string $path): void {
    header('Location: ' . $path, TRUE, 303);
  }

}
