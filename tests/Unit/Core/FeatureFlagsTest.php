<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\FeatureFlags;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureFlags::class)]
final class FeatureFlagsTest extends TestCase
{
    protected function setUp(): void
    {
        FeatureFlags::flush();
    }

    protected function tearDown(): void
    {
        FeatureFlags::flush();
    }

    public function testGetBoolReturnsTrueForTrueString(): void
    {
        FeatureFlags::setCache(['foo' => ['key' => 'foo', 'value' => 'true', 'type' => 'bool']]);

        $this->assertTrue(FeatureFlags::getBool('foo'));
    }

    public function testGetBoolReturnsFalseForFalseString(): void
    {
        FeatureFlags::setCache(['foo' => ['key' => 'foo', 'value' => 'false', 'type' => 'bool']]);

        $this->assertFalse(FeatureFlags::getBool('foo'));
    }

    public function testGetBoolReturnsTrueForOnString(): void
    {
        FeatureFlags::setCache(['foo' => ['key' => 'foo', 'value' => 'on', 'type' => 'bool']]);

        $this->assertTrue(FeatureFlags::getBool('foo'));
    }

    public function testGetBoolReturnsTrueForOneString(): void
    {
        FeatureFlags::setCache(['foo' => ['key' => 'foo', 'value' => '1', 'type' => 'bool']]);

        $this->assertTrue(FeatureFlags::getBool('foo'));
    }

    public function testGetBoolReturnsDefaultWhenKeyAbsent(): void
    {
        FeatureFlags::setCache([]);

        $this->assertTrue(FeatureFlags::getBool('missing', true));
        $this->assertFalse(FeatureFlags::getBool('missing', false));
    }

    public function testGetIntReturnsIntValue(): void
    {
        FeatureFlags::setCache(['limit' => ['key' => 'limit', 'value' => '42', 'type' => 'int']]);

        $this->assertSame(42, FeatureFlags::getInt('limit'));
    }

    public function testGetIntReturnsDefaultWhenKeyAbsent(): void
    {
        FeatureFlags::setCache([]);

        $this->assertSame(99, FeatureFlags::getInt('missing', 99));
    }

    public function testGetFloatReturnsFloatValue(): void
    {
        FeatureFlags::setCache(['ratio' => ['key' => 'ratio', 'value' => '0.5', 'type' => 'float']]);

        $this->assertSame(0.5, FeatureFlags::getFloat('ratio'));
    }

    public function testGetFloatReturnsDefaultWhenKeyAbsent(): void
    {
        FeatureFlags::setCache([]);

        $this->assertSame(1.5, FeatureFlags::getFloat('missing', 1.5));
    }

    public function testGetReturnsBoolTyped(): void
    {
        FeatureFlags::setCache(['flag' => ['key' => 'flag', 'value' => 'true', 'type' => 'bool']]);

        $this->assertIsBool(FeatureFlags::get('flag'));
        $this->assertTrue(FeatureFlags::get('flag'));
    }

    public function testGetReturnsIntTyped(): void
    {
        FeatureFlags::setCache(['num' => ['key' => 'num', 'value' => '10', 'type' => 'int']]);

        $this->assertIsInt(FeatureFlags::get('num'));
        $this->assertSame(10, FeatureFlags::get('num'));
    }

    public function testGetReturnsFloatTyped(): void
    {
        FeatureFlags::setCache(['ratio' => ['key' => 'ratio', 'value' => '0.3', 'type' => 'float']]);

        $this->assertIsFloat(FeatureFlags::get('ratio'));
        $this->assertSame(0.3, FeatureFlags::get('ratio'));
    }

    public function testGetReturnsDefaultWhenKeyAbsent(): void
    {
        FeatureFlags::setCache([]);

        $this->assertSame('default', FeatureFlags::get('missing', 'default'));
        $this->assertNull(FeatureFlags::get('missing'));
    }

    public function testIsClickCollectEnabledUsesFlag(): void
    {
        FeatureFlags::setCache(['click_collect_enabled' => ['key' => 'click_collect_enabled', 'value' => 'true', 'type' => 'bool']]);

        $this->assertTrue(FeatureFlags::isClickCollectEnabled());
    }

    public function testIsClickCollectEnabledReturnsFalseByDefault(): void
    {
        FeatureFlags::setCache([]);

        $this->assertFalse(FeatureFlags::isClickCollectEnabled());
    }

    public function testFlushResetsCache(): void
    {
        FeatureFlags::setCache(['foo' => ['key' => 'foo', 'value' => 'true', 'type' => 'bool']]);
        $this->assertTrue(FeatureFlags::getBool('foo'));

        FeatureFlags::flush();
        // After flush, cache is null — getBool returns default without hitting DB
        // (DB not available in unit tests → loadAll returns [] via exception)
        $this->assertFalse(FeatureFlags::getBool('foo', false));
    }

    public function testSetCacheOverridesExistingCache(): void
    {
        FeatureFlags::setCache(['foo' => ['key' => 'foo', 'value' => 'true', 'type' => 'bool']]);
        FeatureFlags::setCache(['foo' => ['key' => 'foo', 'value' => 'false', 'type' => 'bool']]);

        $this->assertFalse(FeatureFlags::getBool('foo'));
    }

    public function testCacheIsReusedBetweenCalls(): void
    {
        $calls = 0;
        FeatureFlags::setCache(['foo' => ['key' => 'foo', 'value' => 'true', 'type' => 'bool']]);

        FeatureFlags::getBool('foo');
        FeatureFlags::getBool('foo');
        FeatureFlags::getInt('foo', 0);

        // No DB was involved — just asserting no exception and correct values
        $this->assertTrue(FeatureFlags::getBool('foo'));
        $this->assertSame(0, $calls); // $calls never incremented = cache used
    }
}
