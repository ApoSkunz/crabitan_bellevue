<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests unitaires du DI Container léger.
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    /**
     * has() retourne false pour un binding non enregistré.
     */
    public function testHasReturnsFalseWhenNotBound(): void
    {
        $this->assertFalse($this->container->has('NonExistentService'));
    }

    /**
     * has() retourne true après bind().
     */
    public function testHasReturnsTrueAfterBind(): void
    {
        $this->container->bind('MyService', fn() => new \stdClass());
        $this->assertTrue($this->container->has('MyService'));
    }

    /**
     * has() retourne true après singleton().
     */
    public function testHasReturnsTrueAfterSingleton(): void
    {
        $this->container->singleton('MyService', fn() => new \stdClass());
        $this->assertTrue($this->container->has('MyService'));
    }

    /**
     * get() lève RuntimeException si le binding est absent.
     */
    public function testGetThrowsIfNotBound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/NonExistentService/');
        $this->container->get('NonExistentService');
    }

    /**
     * bind() : chaque appel à get() retourne une nouvelle instance.
     */
    public function testBindReturnsNewInstanceEachCall(): void
    {
        $this->container->bind('TransientService', fn() => new \stdClass());

        $a = $this->container->get('TransientService');
        $b = $this->container->get('TransientService');

        $this->assertNotSame($a, $b);
    }

    /**
     * singleton() : get() retourne toujours la même instance.
     */
    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton('SharedService', fn() => new \stdClass());

        $a = $this->container->get('SharedService');
        $b = $this->container->get('SharedService');

        $this->assertSame($a, $b);
    }

    /**
     * La factory reçoit le container comme argument.
     */
    public function testFactoryReceivesContainer(): void
    {
        $receivedContainer = null;

        $this->container->bind('SpyService', function (Container $c) use (&$receivedContainer) {
            $receivedContainer = $c;
            return new \stdClass();
        });

        $this->container->get('SpyService');

        $this->assertSame($this->container, $receivedContainer); // NOSONAR — php:S3415 — ordre correct
    }

    /**
     * Un binding peut résoudre une dépendance imbriquée via get().
     */
    public function testNestedResolution(): void
    {
        $this->container->singleton('Inner', fn() => (object) ['value' => 42]);
        $this->container->singleton('Outer', function (Container $c) {
            return (object) ['inner' => $c->get('Inner')];
        });

        $outer = $this->container->get('Outer');

        $this->assertSame(42, $outer->inner->value);
    }

    /**
     * La factory singleton n'est appelée qu'une seule fois même avec plusieurs get().
     */
    public function testSingletonFactoryCalledOnce(): void
    {
        $callCount = 0;

        $this->container->singleton('CountedService', function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        });

        $this->container->get('CountedService');
        $this->container->get('CountedService');
        $this->container->get('CountedService');

        $this->assertSame(1, $callCount);
    }

    /**
     * La factory transient est appelée à chaque get().
     */
    public function testBindFactoryCalledEachTime(): void
    {
        $callCount = 0;

        $this->container->bind('CountedTransient', function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        });

        $this->container->get('CountedTransient');
        $this->container->get('CountedTransient');

        $this->assertSame(2, $callCount);
    }

    /**
     * bind() peut écraser un singleton existant (réenregistrement).
     */
    public function testRebindOverridesSingleton(): void
    {
        $this->container->singleton('Service', fn() => (object) ['v' => 1]);
        $this->container->bind('Service', fn() => (object) ['v' => 2]);

        $result = $this->container->get('Service');

        $this->assertSame(2, $result->v);
    }
}
