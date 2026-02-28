<?php
/**
 * config.php
 * Configuration constants for real-time features.
 */

// Database connection (adjust to your environment)
// IMPORTANT: Replace these example values with your actual credentials.
// Never commit real credentials to version control; use environment variables in production.
define('DB_HOST', 'localhost');
define('DB_NAME', 'kep_ward19');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_DEBUG', false);

// Rate limiting for real-time endpoints (requests per minute per IP)
define('RATE_LIMIT_REQUESTS', 60);
define('RATE_LIMIT_WINDOW',   60);

// Form state persistence (seconds before saved state expires)
define('FORM_STATE_TTL', 86400); // 24 hours

// SSE: maximum push iterations per connection (~30 min at 30s interval)
define('SSE_MAX_ITERATIONS', 60);
