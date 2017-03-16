<?php namespace Limoncello\Tests\Core\Routing;

/**
 * Copyright 2015-2016 info@neomerx.com (www.neomerx.com)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use FastRoute\DataGenerator;
use FastRoute\DataGenerator\CharCountBased as CharCountBasedGenerator;
use FastRoute\DataGenerator\GroupCountBased as GroupCountBasedGenerator;
use FastRoute\DataGenerator\GroupPosBased as GroupPosBasedGenerator;
use FastRoute\DataGenerator\MarkBased as MarkBasedGenerator;
use FastRoute\RouteParser;
use Limoncello\Core\Contracts\Application\SapiInterface;
use Limoncello\Core\Contracts\Routing\GroupInterface;
use Limoncello\Core\Contracts\Routing\RouteInterface;
use Limoncello\Core\Contracts\Routing\RouterInterface;
use Limoncello\Core\Routing\Dispatcher\CharCountBased;
use Limoncello\Core\Routing\Dispatcher\GroupCountBased;
use Limoncello\Core\Routing\Dispatcher\GroupPosBased;
use Limoncello\Core\Routing\Dispatcher\MarkBased;
use Limoncello\Core\Routing\Group;
use Limoncello\Core\Routing\Router;
use Limoncello\Tests\Core\TestCase;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

/**
 * @package Limoncello\Tests\Core
 */
class RouterTest extends TestCase
{
    /** Test route name */
    const ROUTE_NAME_DELETE_POST = 'deletePost';

    /**
     * Test match routes.
     */
    public function testMatchForGroupCountBasedRouter()
    {
        $this->checkMatchRoutes($this->createGroupCountBasedRouter());
    }

    /**
     * Test match routes.
     */
    public function testMatchForGroupPosBasedRouter()
    {
        $this->checkMatchRoutes($this->createGroupPosBasedRouter());
    }

    /**
     * Test match routes.
     */
    public function testMatchForCharCountBasedRouter()
    {
        $this->checkMatchRoutes($this->createCharCountBasedRouter());
    }

    /**
     * Test match routes.
     */
    public function testMatchForMarkBasedRouter()
    {
        $this->checkMatchRoutes($this->createMarkBasedRouter());
    }

    /**
     * Test loading of cached data.
     */
    public function testLoadCachedRoutes()
    {
        $cached = $this->createGroupCountBasedRouter()->getCachedRoutes($this->createGroup());

        $router = $this->createGroupCountBasedRouter();
        $router->loadCachedRoutes($cached);

        $this->assertEquals(
            [RouterInterface::MATCH_FOUND, null, [], [self::class, 'postsIndex'], [], [], null],
            $router->match('GET', '/posts')
        );
    }

    /**
     * @expectedException \LogicException
     */
    public function testRoutesAreNotLoaded2()
    {
        $this->createGroupCountBasedRouter()->match('GET', '/');
    }

    public function testXXX()
    {
        $router = $this->createGroupCountBasedRouter();
        $router->loadCachedRoutes($router->getCachedRoutes($this->createGroup()));

        $request = new ServerRequest([], [], new Uri('http://server.foo/bla'));
        $this->assertEquals('http://server.foo/posts/{id}', $router->get($request, self::ROUTE_NAME_DELETE_POST));
        $this->assertEquals(
            'http://server.foo/posts/123',
            $router->get($request, self::ROUTE_NAME_DELETE_POST, ['id' => 123])
        );
    }

    /**
     * @param RouterInterface $router
     */
    private function checkMatchRoutes(RouterInterface $router)
    {
        $cachedRoutes = $router->getCachedRoutes($this->createGroup());
        $router->loadCachedRoutes($cachedRoutes);

        $this->assertEquals('/posts/{id:\d+}', $router->getUriPath(self::ROUTE_NAME_DELETE_POST));

        $this->assertEquals(
            [RouterInterface::MATCH_FOUND, null, [], [self::class, 'homeIndex'], [], [], null],
            $router->match('GET', '/')
        );

        $this->assertEquals(
            [RouterInterface::MATCH_FOUND, null, [], [self::class, 'postsIndex'], [], [], null],
            $router->match('GET', '/posts')
        );

        $this->assertEquals(
            [
                RouterInterface::MATCH_FOUND,
                null,
                [],
                [self::class, 'postsCreate'],
                [self::class . '::createPostMiddleware'],
                [self::class . '::createPostConfigurator'],
                [self::class, 'createRequest']
            ],
            $router->match('POST', '/posts')
        );

        $this->assertEquals(
            [RouterInterface::MATCH_NOT_FOUND, null, null, null, null, null, null],
            $router->match('GET', '/non-existent')
        );

        $this->assertEquals(
            [RouterInterface::MATCH_METHOD_NOT_ALLOWED, ['GET', 'POST'], null, null, null, null, null],
            $router->match('PATCH', '/')
        );
    }

    /**
     * @return GroupInterface
     */
    private function createGroup()
    {
        return (new Group([GroupInterface::PARAM_REQUEST_FACTORY => null]))
            ->get('/', [self::class, 'homeIndex'])
            ->group('posts', function (GroupInterface $group) {
                $group
                    ->get('', [self::class, 'postsIndex'])
                    ->post('', [self::class, 'postsCreate'], [
                        RouteInterface::PARAM_MIDDLEWARE_LIST         => [self::class . '::createPostMiddleware'],
                        RouteInterface::PARAM_CONTAINER_CONFIGURATORS => [self::class . '::createPostConfigurator'],
                        RouteInterface::PARAM_REQUEST_FACTORY         => [self::class, 'createRequest'],
                    ])
                    ->delete('{id:\d+}', [self::class, 'postsDelete'], [
                            RouteInterface::PARAM_NAME => self::ROUTE_NAME_DELETE_POST,
                    ]);
            })
            ->post('', [self::class, 'createNews']);
    }

    /**
     * @return RouterInterface
     */
    private function createGroupCountBasedRouter()
    {
        return new Router(GroupCountBasedGenerator::class, GroupCountBased::class);
    }

    /**
     * @return RouterInterface
     */
    private function createGroupPosBasedRouter()
    {
        return new Router(GroupPosBasedGenerator::class, GroupPosBased::class);
    }

    /**
     * @return RouterInterface
     */
    private function createCharCountBasedRouter()
    {
        return new Router(CharCountBasedGenerator::class, CharCountBased::class);
    }

    /**
     * @return RouterInterface
     */
    private function createMarkBasedRouter()
    {
        return new Router(MarkBasedGenerator::class, MarkBased::class);
    }

    public static function homeIndex()
    {
        // dummy for tests
    }

    public static function createNews()
    {
        // dummy for tests
    }

    public static function postsIndex()
    {
        // dummy for tests
    }

    public static function postsCreate()
    {
        // dummy for tests
    }

    /**
     * @param mixed $idx
     */
    public static function postsDelete($idx)
    {
        $idx ?: null;

        // dummy for tests
    }

    /**
     * @param SapiInterface $sapi
     */
    public static function createRequest(SapiInterface $sapi)
    {
        $sapi ?: null;
        // dummy for tests
    }

    public static function createPostMiddleware()
    {
        // dummy for tests
    }

    public static function createPostConfigurator()
    {
        // dummy for tests
    }
}
