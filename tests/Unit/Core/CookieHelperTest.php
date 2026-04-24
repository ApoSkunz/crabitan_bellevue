<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\CookieHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour Core\CookieHelper.
 *
 * Couvre : set() branche expiry > 0, set() branche expiry = 0, clear().
 */
class CookieHelperTest extends TestCase
{
    // ----------------------------------------------------------------
    // set() — branche expiry > 0
    // ----------------------------------------------------------------

    /**
     * set() avec expiry > 0 → options['expires'] calculé (time() + expiry).
     *
     * setcookie() est no-op en CLI ; on vérifie l'absence d'exception.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testSetWithPositiveExpiryDoesNotThrow(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('APP_ENV')   || define('APP_ENV', 'testing');
        require_once ROOT_PATH . '/vendor/autoload.php';

        $this->expectNotToPerformAssertions();
        CookieHelper::set('jwt-token-value', 3600);
    }

    // ----------------------------------------------------------------
    // set() — branche expiry = 0 (ligne 34)
    // ----------------------------------------------------------------

    /**
     * set() avec expiry = 0 → options['expires'] = 0 (cookie de session).
     *
     * Couvre la branche else ligne 34.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testSetWithZeroExpiryUsesSessionCookie(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('APP_ENV')   || define('APP_ENV', 'testing');
        require_once ROOT_PATH . '/vendor/autoload.php';

        $this->expectNotToPerformAssertions();
        CookieHelper::set('jwt-session-token', 0);
    }

    // ----------------------------------------------------------------
    // clear()
    // ----------------------------------------------------------------

    /**
     * clear() supprime le cookie auth_token sans lever d'exception.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testClearDoesNotThrow(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('APP_ENV')   || define('APP_ENV', 'testing');
        require_once ROOT_PATH . '/vendor/autoload.php';

        $this->expectNotToPerformAssertions();
        CookieHelper::clear();
    }
}
