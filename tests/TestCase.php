<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application, bootstrapped for testing.
     *
     * Safety guard: a stale bootstrap/cache/config.php (built from the real
     * .env) makes Laravel ignore phpunit.xml env vars entirely — tests would
     * then run against the LIVE database and wipe real data. Detect that
     * situation and refuse to boot rather than destroy data.
     */
    public function createApplication()
    {
        $cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

        if (is_file($cachedConfig)) {
            $cached = require $cachedConfig;
            $dbDatabase = $cached['database']['connections']['mysql']['database'] ?? null;

            if ($dbDatabase !== null && $dbDatabase !== 'u1690p3409_bhasebook_test') {
                fwrite(STDERR,
                    "\n  REFUSING TO RUN: bootstrap/cache/config.php bakes the live DB ({$dbDatabase}).\n".
                    "  Tests would run against the LIVE database and destroy real data.\n".
                    "  Run `php artisan config:clear` (or rm bootstrap/cache/config.php) first,\n".
                    "  or use ./run-tests.sh which handles this automatically.\n\n");
                exit(1);
            }
        }

        return parent::createApplication();
    }
}
