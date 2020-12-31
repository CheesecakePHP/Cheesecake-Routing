<?php


use App\Components\Path\Controller;
use Cheesecake\Routing\Exception\ControllerNotExistsException;
use Cheesecake\Routing\Exception\MalformedActionException;
use Cheesecake\Routing\Exception\MethodNotExistsException;
use Cheesecake\Routing\Exception\RouteIsEmptyException;
use Cheesecake\Routing\Exception\RouteNotDefinedException;
use Cheesecake\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{

    private $Router;

    private $Mocks;

    public function setUp(): void
    {
        parent::setUp();

        require_once('../src/Router.php');
        require_once('../src/RouteInterface.php');
        require_once('../src/Route.php');
        require_once('../src/Exception/ControllerNotExistsException.php');
        require_once('../src/Exception/MethodNotExistsException.php');
        require_once('../src/Exception/MalformedActionException.php');
        require_once('../src/Exception/RouteIsEmptyException.php');
        require_once('../src/Exception/RouteNotDefinedException.php');
        require_once('mocks/PathController.php');
        require_once('mocks/FooMiddleware.php');
        require_once('mocks/ValidatorInterface.php');
        require_once('mocks/NumberValidator.php');

        $this->Mocks = new \stdClass();

        $this->Mocks->FooMiddleware = $this->createMock(\App\Middleware\Foo::class);
        $PathController = $this->createMock(Controller::class);

        if(!class_exists(\App\Components\Path\Controller::class)) {
            class_alias(get_class($PathController), \App\Components\Path\Controller::class);
        }

        $this->Mocks->PathController = new \App\Components\Path\Controller();
    }

    /**
     * @dataProvider routesProvider
     */
    public function testCanAddRoute($httpVerb, $route)
    {
        Router::$httpVerb($route, 'Path@To', ['middleware' => get_class($this->Mocks->FooMiddleware)]);
        $Route = Router::$httpVerb($route);

        $routeOptions = $Route->getOptions();

        self::assertEquals('Path@To', $Route->getAction());
        self::assertIsArray($routeOptions);
        self::assertArrayHasKey('middleware', $routeOptions);
        self::assertEquals(get_class($this->Mocks->FooMiddleware), $routeOptions['middleware']);
    }

    /**
     * @dataProvider routesProvider
     */
    public function testCanNotAddRoute($httpVerb)
    {
        self::expectException(RouteIsEmptyException::class);

        Router::$httpVerb('', 'Path@To');
    }

    /**
     * @dataProvider routesProvider
     */
    public function testCanRoute($httpVerb, $route)
    {
        Router::$httpVerb($route, 'Path@To');

        $route = Router::route(strtoupper($httpVerb), $route);

        self::assertIsArray($route);
        self::assertArrayHasKey('controller', $route);
        self::assertArrayHasKey('method', $route);
        self::assertInstanceOf(\App\Components\Path\Controller::class, $route['controller']);
        self::assertEquals('To', $route['method']);
    }

    /**
     * @dataProvider routesProvider
     */
    public function testCanNotRoute($httpVerb, $route)
    {
        self::expectException(RouteNotDefinedException::class);
        self::expectExceptionMessage('Route "v2/'. $route .'" not defined');

        Router::$httpVerb($route, 'Path@to');
        $route = Router::route(strtoupper($httpVerb), 'v2/'. $route);
    }

    /**
     * @dataProvider routesProvider
     */
    public function testMalformedActionException($httpVerb, $route)
    {
        self::expectException(MalformedActionException::class);

        Router::$httpVerb($route, 'Path');
        $route = Router::route(strtoupper($httpVerb), $route);
    }

    /**
     * @dataProvider routesProvider
     */
    public function testControllerNotExistsException($httpVerb, $route)
    {
        self::expectException(ControllerNotExistsException::class);

        Router::$httpVerb('foo/bar', 'Foo@bar');
        $route = Router::route(strtoupper($httpVerb), 'foo/bar');
    }

    /**
     * @dataProvider routesProvider
     */
    public function testMethodNotExistsException($httpVerb)
    {
        self::expectException(MethodNotExistsException::class);

        Router::$httpVerb('path/foo', 'Path@foo');
        $route = Router::route(strtoupper($httpVerb), 'path/foo');
    }

    /**
     * @dataProvider routesProvider
     */
    public function testMiddleware($httpVerb, $route)
    {
        $this->Mocks->FooMiddleware->method('handle')->willReturn($this->returnValue(true));

        Router::$httpVerb($route, 'Path@to', [ 'middleware' => get_class($this->Mocks->FooMiddleware) ]);
        $Route = Router::$httpVerb($route);

        $route = Router::route(strtoupper($httpVerb), $route);

        self::assertArrayNotHasKey('error', $route);
    }

    /**
     * @dataProvider routesProvider
     */
    public function testMiddlewareFailed($httpVerb, $route)
    {
        $this->Mocks->FooMiddleware->method('handle')->willReturn($this->returnValue(false));

        Router::$httpVerb($route, 'Path@to', [ 'middleware' => get_class($this->Mocks->FooMiddleware) ]);
        $Route = Router::$httpVerb($route);

        $route = Router::route(strtoupper($httpVerb), $route);

        self::assertArrayNotHasKey('error', $route);
    }

    public function routesProvider()
    {
        return [
            ['get', '/path/to/endpoint'],
            ['post', '/path/to/endpoint'],
            ['put', '/path/to/endpoint'],
            ['patch', '/path/to/endpoint'],
            ['delete', '/path/to/endpoint'],
            ['any', '/path/to/endpoint']
        ];
    }

    public function testCanGroupRoutes()
    {
        Router::group([
            'prefix' => 'v1/',
            'middleware' => 'foo'
        ], function() {
            Router::get('path/to/endpoint', 'Path@To');
            Router::post('path/to/endpoint', 'Path@To');
            Router::put('path/to/endpoint', 'Path@To');
            Router::patch('path/to/endpoint', 'Path@To');
            Router::delete('path/to/endpoint', 'Path@To');
            Router::any('path/to/endpoint', 'Path@To');
        });

        $GetRoute = Router::get('v1/path/to/endpoint');
        $PostRoute = Router::post('v1/path/to/endpoint');
        $PutRoute = Router::put('v1/path/to/endpoint');
        $PatchRoute = Router::patch('v1/path/to/endpoint');
        $DeleteRoute = Router::delete('v1/path/to/endpoint');
        $AnyRoute = Router::any('v1/path/to/endpoint');

        self::assertEquals('Path@To', $GetRoute->getAction());
        self::assertEquals('Path@To', $PostRoute->getAction());
        self::assertEquals('Path@To', $PutRoute->getAction());
        self::assertEquals('Path@To', $PatchRoute->getAction());
        self::assertEquals('Path@To', $DeleteRoute->getAction());
        self::assertEquals('Path@To', $AnyRoute->getAction());

        self::assertIsArray($GetRoute->getOptions());
        self::assertIsArray($PostRoute->getOptions());
        self::assertIsArray($PutRoute->getOptions());
        self::assertIsArray($PatchRoute->getOptions());
        self::assertIsArray($DeleteRoute->getOptions());
        self::assertIsArray($AnyRoute->getOptions());

        self::assertArrayHasKey('middleware', $GetRoute->getOptions());
        self::assertArrayHasKey('middleware', $PostRoute->getOptions());
        self::assertArrayHasKey('middleware', $PutRoute->getOptions());
        self::assertArrayHasKey('middleware', $PatchRoute->getOptions());
        self::assertArrayHasKey('middleware', $DeleteRoute->getOptions());
        self::assertArrayHasKey('middleware', $AnyRoute->getOptions());

        self::assertEquals('foo', ($GetRoute->getOptions())['middleware']);
        self::assertEquals('foo', ($PostRoute->getOptions())['middleware']);
        self::assertEquals('foo', ($PutRoute->getOptions())['middleware']);
        self::assertEquals('foo', ($PatchRoute->getOptions())['middleware']);
        self::assertEquals('foo', ($DeleteRoute->getOptions())['middleware']);
        self::assertEquals('foo', ($AnyRoute->getOptions())['middleware']);
    }

    public function testCanParameterMatchRules()
    {
        Router::get('path/to/{id}', 'Path@To')->where([ 'id' => '([0-9]+)' ]);

        $route = Router::route('GET', '/path/to/1');

        self::assertNotFalse($route);
    }

    public function testCanNotParameterMatchRules()
    {
        self::expectException(RouteNotDefinedException::class);

        Router::get('path/to/{id}', 'Path@To')->where([ 'id' => '([0-9]+)' ]);

        $route = Router::route('GET', '/path/to/username');
    }

    public function testWhereWithValidator()
    {
        Router::get('path/to/{id}', 'Path@To')->where([
            'id' => \Cheesecake\Validator\Number::class
        ]);

        $route = Router::route('GET', '/path/to/1');

        self::assertNotFalse($route);
    }

    public function testWhereWithValidatorFailed()
    {
        self::expectException(RouteNotDefinedException::class);

        Router::get('path/to/{id}', 'Path@To')->where([
            'id' => \Cheesecake\Validator\Number::class
        ]);

        $route = Router::route('GET', '/path/to/one');
    }

}
