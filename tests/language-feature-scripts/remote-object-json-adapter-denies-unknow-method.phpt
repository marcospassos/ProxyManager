--TEST--
Verifies that generated remote object can call public property
--SKIPIF--
<?php
if (PHP_VERSION_ID >= 70000) {
    echo 'Skip on PHP 7+ as PHP4 constructors are deprecated';
}
?>
--FILE--
<?php

require_once __DIR__ . '/init.php';

use ProxyManager\Factory\RemoteObject\AdapterInterface;
use Zend\Json\Server\Client;

interface FooServiceInterface
{
    public function foo();
}

class Foo implements FooServiceInterface
{
    public $foo = "baz";
    
    public function foo()
    {
        return 'bar';
    }
}

class CustomAdapter implements AdapterInterface
{
    public function call($wrappedClass, $method, array $params = [])
    {
        return 'baz';
    }
}

$factory = new \ProxyManager\Factory\RemoteObjectFactory(new CustomAdapter(), $configuration);
$proxy   = $factory->createProxy('ProxyManagerTestAsset\RemoteProxy\FooServiceInterface');

var_dump($proxy->foo());
var_dump($proxy->unknown());
?>
--EXPECTF--
string(3) "baz"

%SFatal error: Call to undefined method %s::unknown%S in %s on line %d