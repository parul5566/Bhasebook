#!/usr/bin/env bash
# Test runner for Bhasebook.
#
# Tests run against a DEDICATED test database (u1690p3409_bhasebook_test),
# never the live one. This script:
#   1. creates the test DB if missing (needs root; falls back gracefully)
#   2. clears any config cache (a cached config ignores phpunit.xml env vars)
#   3. runs the suite
#   4. rebuilds the production config cache afterwards

set -euo pipefail
cd /workspace

# 1. Ensure the test database exists (one-time; needs local root)
if ! mysql -u root -e "USE u1690p3409_bhasebook_test" 2>/dev/null; then
    echo "→ Creating test database"
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS u1690p3409_bhasebook_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        GRANT ALL PRIVILEGES ON u1690p3409_bhasebook_test.* TO 'u1690p3409_user'@'%';
        GRANT ALL PRIVILEGES ON u1690p3409_bhasebook_test.* TO 'u1690p3409_user'@'localhost';
        FLUSH PRIVILEGES;"
fi

# 2. A cached config would override phpunit.xml — always clear first
echo "→ Clearing config cache"
rm -f bootstrap/cache/config.php bootstrap/cache/routes-*.php
php artisan optimize:clear >/dev/null 2>&1 || true

# 3. Run the suite against the test DB
echo "→ Running tests"
APP_ENV=testing php artisan test "$@"
STATUS=$?

# 4. Restore the production config cache for the running app
php artisan config:cache >/dev/null 2>&1 || true
php artisan route:cache >/dev/null 2>&1 || true

exit $STATUS
