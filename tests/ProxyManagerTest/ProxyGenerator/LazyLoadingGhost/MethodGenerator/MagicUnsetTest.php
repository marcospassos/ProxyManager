<?php

declare(strict_types=1);

namespace ProxyManagerTest\ProxyGenerator\LazyLoadingGhost\MethodGenerator;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ProxyManager\ProxyGenerator\LazyLoadingGhost\MethodGenerator\MagicUnset;
use ProxyManager\ProxyGenerator\LazyLoadingGhost\PropertyGenerator\PrivatePropertiesMap;
use ProxyManager\ProxyGenerator\LazyLoadingGhost\PropertyGenerator\ProtectedPropertiesMap;
use ProxyManager\ProxyGenerator\PropertyGenerator\PublicPropertiesMap;
use ProxyManagerTestAsset\ClassWithMagicMethods;
use ProxyManagerTestAsset\ProxyGenerator\LazyLoading\MethodGenerator\ClassWithTwoPublicProperties;
use ReflectionClass;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\PropertyGenerator;

/**
 * Tests for {@see \ProxyManager\ProxyGenerator\LazyLoadingGhost\MethodGenerator\MagicUnset}
 *
 * @group Coverage
 */
class MagicUnsetTest extends TestCase
{
    /** @var PropertyGenerator|MockObject */
    protected $initializer;

    /** @var MethodGenerator|MockObject */
    protected $initMethod;

    /** @var PublicPropertiesMap|MockObject */
    protected $publicProperties;

    /** @var ProtectedPropertiesMap|MockObject */
    protected $protectedProperties;

    /** @var PrivatePropertiesMap|MockObject */
    protected $privateProperties;

    /** @var string */
    private $expectedCode = <<<'PHP'
$this->foo && $this->baz('__unset', array('name' => $name));

if (isset(self::$bar[$name])) {
    unset($this->$name);

    return;
}

if (isset(self::$baz[$name])) {
    // check protected property access via compatible class
    $callers      = debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
    $caller       = isset($callers[1]) ? $callers[1] : [];
    $object       = isset($caller['object']) ? $caller['object'] : '';
    $expectedType = self::$baz[$name];

    if ($object instanceof $expectedType) {
        unset($this->$name);

        return;
    }

    $class = isset($caller['class']) ? $caller['class'] : '';

    if ($class === $expectedType || is_subclass_of($class, $expectedType) || $class === 'ReflectionProperty') {
        unset($this->$name);

        return;
    }
} elseif (isset(self::$tab[$name])) {
    // check private property access via same class
    $callers = debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
    $caller  = isset($callers[1]) ? $callers[1] : [];
    $class   = isset($caller['class']) ? $caller['class'] : '';

    static $accessorCache = [];

    if (isset(self::$tab[$name][$class])) {
        $cacheKey = $class . '#' . $name;
        $accessor = isset($accessorCache[$cacheKey])
            ? $accessorCache[$cacheKey]
            : $accessorCache[$cacheKey] = \Closure::bind(function ($instance) use ($name) {
                unset($instance->$name);
            }, null, $class);

        return $accessor($this);
    }

    if ('ReflectionProperty' === $class) {
        $tmpClass = key(self::$tab[$name]);
        $cacheKey = $tmpClass . '#' . $name;
        $accessor = isset($accessorCache[$cacheKey])
            ? $accessorCache[$cacheKey]
            : $accessorCache[$cacheKey] = \Closure::bind(function ($instance) use ($name) {
                unset($instance->$name);
            }, null, $tmpClass);

        return $accessor($this);
    }
}
%A
PHP;

    /**
     * {@inheritDoc}
     */
    protected function setUp() : void
    {
        $this->initializer         = $this->createMock(PropertyGenerator::class);
        $this->initMethod          = $this->createMock(MethodGenerator::class);
        $this->publicProperties    = $this
            ->getMockBuilder(PublicPropertiesMap::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->protectedProperties = $this
            ->getMockBuilder(ProtectedPropertiesMap::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->privateProperties   = $this
            ->getMockBuilder(PrivatePropertiesMap::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->initializer->method('getName')->willReturn('foo');
        $this->initMethod->method('getName')->willReturn('baz');
        $this->publicProperties->method('isEmpty')->willReturn(false);
        $this->publicProperties->method('getName')->willReturn('bar');
        $this->protectedProperties->method('getName')->willReturn('baz');
        $this->privateProperties->method('getName')->willReturn('tab');
    }

    /**
     * @covers \ProxyManager\ProxyGenerator\LazyLoadingGhost\MethodGenerator\MagicUnset::__construct
     */
    public function testBodyStructure() : void
    {
        $magicIsset = new MagicUnset(
            new ReflectionClass(ClassWithTwoPublicProperties::class),
            $this->initializer,
            $this->initMethod,
            $this->publicProperties,
            $this->protectedProperties,
            $this->privateProperties
        );

        self::assertSame('__unset', $magicIsset->getName());
        self::assertCount(1, $magicIsset->getParameters());
        self::assertStringMatchesFormat($this->expectedCode, $magicIsset->getBody());
    }

    /**
     * @covers \ProxyManager\ProxyGenerator\LazyLoadingGhost\MethodGenerator\MagicUnset::__construct
     */
    public function testBodyStructureWithOverriddenMagicGet() : void
    {
        $magicIsset = new MagicUnset(
            new ReflectionClass(ClassWithMagicMethods::class),
            $this->initializer,
            $this->initMethod,
            $this->publicProperties,
            $this->protectedProperties,
            $this->privateProperties
        );

        self::assertSame('__unset', $magicIsset->getName());
        self::assertCount(1, $magicIsset->getParameters());

        $body = $magicIsset->getBody();

        self::assertStringMatchesFormat($this->expectedCode, $body);
        self::assertStringMatchesFormat('%Areturn parent::__unset($name);', $body);
    }
}
