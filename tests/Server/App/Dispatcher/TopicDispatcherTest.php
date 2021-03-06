<?php declare(strict_types=1);

namespace Gos\Bundle\WebSocketBundle\Tests\Server\App\Dispatcher;

use Gos\Bundle\PubSubRouterBundle\Router\Route;
use Gos\Bundle\PubSubRouterBundle\Router\RouterInterface;
use Gos\Bundle\WebSocketBundle\Router\WampRequest;
use Gos\Bundle\WebSocketBundle\Router\WampRouter;
use Gos\Bundle\WebSocketBundle\Server\App\Dispatcher\TopicDispatcher;
use Gos\Bundle\WebSocketBundle\Server\App\Registry\TopicRegistry;
use Gos\Bundle\WebSocketBundle\Server\Exception\FirewallRejectionException;
use Gos\Bundle\WebSocketBundle\Server\Exception\PushUnsupportedException;
use Gos\Bundle\WebSocketBundle\Topic\PushableTopicInterface;
use Gos\Bundle\WebSocketBundle\Topic\SecuredTopicInterface;
use Gos\Bundle\WebSocketBundle\Topic\TopicInterface;
use Gos\Bundle\WebSocketBundle\Topic\TopicManager;
use Gos\Bundle\WebSocketBundle\Topic\TopicPeriodicTimer;
use Gos\Bundle\WebSocketBundle\Topic\TopicPeriodicTimerInterface;
use Gos\Bundle\WebSocketBundle\Topic\TopicPeriodicTimerTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\Topic;
use Ratchet\Wamp\WampConnection;
use Symfony\Component\HttpFoundation\ParameterBag;

final class TopicDispatcherTest extends TestCase
{
    /**
     * @var TopicRegistry
     */
    private $topicRegistry;

    /**
     * @var WampRouter
     */
    private $wampRouter;

    /**
     * @var MockObject|TopicPeriodicTimer
     */
    private $topicPeriodicTimer;

    /**
     * @var MockObject|TopicManager
     */
    private $topicManager;

    /**
     * @var TestLogger
     */
    private $logger;

    /**
     * @var TopicDispatcher
     */
    private $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->topicRegistry = new TopicRegistry();
        $this->wampRouter = new WampRouter($this->createMock(RouterInterface::class));
        $this->topicPeriodicTimer = $this->createMock(TopicPeriodicTimer::class);
        $this->topicManager = $this->createMock(TopicManager::class);

        $this->logger = new TestLogger();

        $this->dispatcher = new TopicDispatcher($this->topicRegistry, $this->wampRouter, $this->topicPeriodicTimer, $this->topicManager);
        $this->dispatcher->setLogger($this->logger);
    }

    public function testAWebsocketSubscriptionIsDispatchedToItsHandler(): void
    {
        $handler = new class() implements TopicInterface {
            private $called = false;

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                $this->called = true;
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }
        };

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $connection = $this->createMock(WampConnection::class);
        $topic = $this->createMock(Topic::class);

        $this->dispatcher->onSubscribe($connection, $topic, $request);

        $this->assertTrue($handler->wasCalled());
    }

    public function testAWebsocketPushIsDispatchedToItsHandler(): void
    {
        $handler = new class() implements TopicInterface, PushableTopicInterface {
            private $called = false;

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPush(Topic $topic, WampRequest $request, $data, string $provider): void
            {
                $this->called = true;
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }
        };

        $this->topicManager->expects($this->once())
            ->method('getTopic')
            ->with('topic.handler')
            ->willReturn($this->createMock(Topic::class));

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $this->dispatcher->onPush($request, 'test', 'provider');

        $this->assertTrue($handler->wasCalled());
    }

    public function testAWebsocketPushFailsIfTheHandlerDoesNotImplementTheRequiredInterface(): void
    {
        $this->expectException(PushUnsupportedException::class);
        $this->expectExceptionMessage('The "topic.handler" topic does not support push notifications');

        $handler = new class() implements TopicInterface {
            private $called = false;

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }
        };

        $this->topicManager->expects($this->once())
            ->method('getTopic')
            ->with('topic.handler')
            ->willReturn($this->createMock(Topic::class));

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $this->dispatcher->onPush($request, 'test', 'provider');
    }

    public function testAWebsocketUnsubscriptionIsDispatchedToItsHandler(): void
    {
        $handler = new class() implements TopicInterface {
            private $called = false;

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                $this->called = true;
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }
        };

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $connection = $this->createMock(WampConnection::class);
        $topic = $this->createMock(Topic::class);

        $this->dispatcher->onUnSubscribe($connection, $topic, $request);

        $this->assertTrue($handler->wasCalled());
    }

    public function testAWebsocketPublishIsDispatchedToItsHandler(): void
    {
        $handler = new class() implements TopicInterface {
            private $called = false;

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                $this->called = true;
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }
        };

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $connection = $this->createMock(WampConnection::class);
        $topic = $this->createMock(Topic::class);

        $this->dispatcher->onPublish($connection, $topic, $request, 'test', [], []);

        $this->assertTrue($handler->wasCalled());
    }

    public function testADispatchToASecuredTopicHandlerIsCompleted(): void
    {
        $handler = new class() implements TopicInterface, SecuredTopicInterface {
            private $called = false;
            private $secured = false;

            public function secure(?ConnectionInterface $conn, Topic $topic, WampRequest $request, $payload = null, ?array $exclude = null, ?array $eligible = null, ?string $provider = null): void
            {
                $this->secured = true;
            }

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                $this->called = true;
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }

            public function wasSecured(): bool
            {
                return $this->secured;
            }
        };

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $connection = $this->createMock(WampConnection::class);
        $topic = $this->createMock(Topic::class);

        $this->dispatcher->dispatch(TopicDispatcher::PUBLISH, $connection, $topic, $request, 'test', [], []);

        $this->assertTrue($handler->wasCalled());
        $this->assertTrue($handler->wasSecured());
    }

    public function testADispatchToAnUnregisteredPeriodicTopicTimerIsCompleted(): void
    {
        $handler = new class() implements TopicInterface, TopicPeriodicTimerInterface {
            use TopicPeriodicTimerTrait;

            private $called = false;
            private $registered = false;

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                $this->called = true;
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function registerPeriodicTimer(Topic $topic): void
            {
                $this->registered = true;
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }

            public function wasRegistered(): bool
            {
                return $this->registered;
            }
        };

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $connection = $this->createMock(WampConnection::class);

        $topic = $this->createMock(Topic::class);
        $topic->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $this->topicPeriodicTimer->expects($this->once())
            ->method('isRegistered')
            ->with($handler)
            ->willReturn(false);

        $this->dispatcher->dispatch(TopicDispatcher::PUBLISH, $connection, $topic, $request, 'test', [], []);

        $this->assertTrue($handler->wasCalled());
        $this->assertTrue($handler->wasRegistered());
    }

    public function testPeriodicTimersAreClearedWhenAnEmptyTopicIsUnsubscribed(): void
    {
        $handler = new class() implements TopicInterface {
            private $called = false;

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                $this->called = true;
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }
        };

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $connection = $this->createMock(WampConnection::class);

        $topic = $this->createMock(Topic::class);
        $topic->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $this->topicPeriodicTimer->expects($this->once())
            ->method('clearPeriodicTimer')
            ->with($handler);

        $this->dispatcher->dispatch(TopicDispatcher::UNSUBSCRIPTION, $connection, $topic, $request);

        $this->assertTrue($handler->wasCalled());
    }

    public function testADispatchFailsWhenItsHandlerIsNotInTheRegistry(): void
    {
        $handler = new class() implements TopicInterface {
            private $called = false;

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }
        };

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $connection = $this->createMock(WampConnection::class);
        $topic = $this->createMock(Topic::class);

        $this->dispatcher->onPublish($connection, $topic, $request, 'test', [], []);

        $this->assertFalse($handler->wasCalled());

        $this->assertTrue($this->logger->hasErrorThatContains('Could not find topic dispatcher in registry for callback "topic.handler".'));
    }

    public function testTheConnectionIsClosedIfATopicCannotBeSecured(): void
    {
        $handler = new class() implements TopicInterface, SecuredTopicInterface {
            private $called = false;
            private $secured = false;

            public function secure(?ConnectionInterface $conn, Topic $topic, WampRequest $request, $payload = null, ?array $exclude = null, ?array $eligible = null, ?string $provider = null): void
            {
                throw new FirewallRejectionException('Access denied');
            }

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }

            public function wasSecured(): bool
            {
                return $this->secured;
            }
        };

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $connection = $this->createMock(WampConnection::class);
        $connection->expects($this->once())
            ->method('callError');

        $connection->expects($this->once())
            ->method('close');

        $topic = $this->createMock(Topic::class);
        $topic->expects($this->once())
            ->method('getId')
            ->willReturn('topic');

        $this->dispatcher->dispatch(TopicDispatcher::PUBLISH, $connection, $topic, $request, 'test', [], []);

        $this->assertFalse($handler->wasCalled());
        $this->assertFalse($handler->wasSecured());

        $this->assertTrue($this->logger->hasErrorThatContains('Access denied'));
    }

    public function testAnExceptionFromAHandlerIsCaughtAndProcessed(): void
    {
        $handler = new class() implements TopicInterface {
            private $called = false;

            public function onSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onUnSubscribe(ConnectionInterface $connection, Topic $topic, WampRequest $request): void
            {
                throw new \RuntimeException('Not expected to be called.');
            }

            public function onPublish(ConnectionInterface $connection, Topic $topic, WampRequest $request, $event, array $exclude, array $eligible): void
            {
                $this->called = true;

                throw new \Exception('Testing.');
            }

            public function getName(): string
            {
                return 'topic.handler';
            }

            public function wasCalled(): bool
            {
                return $this->called;
            }
        };

        $this->topicRegistry->addTopic($handler);

        $route = new Route('hello/world', 'topic.handler');

        $request = new WampRequest('hello.world', $route, new ParameterBag(), 'topic.handler');

        $connection = $this->createMock(WampConnection::class);
        $connection->expects($this->once())
            ->method('callError');

        $topic = $this->createMock(Topic::class);
        $topic->expects($this->any())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator());

        $this->dispatcher->dispatch(TopicDispatcher::PUBLISH, $connection, $topic, $request, 'test', [], []);

        $this->assertTrue($handler->wasCalled());

        $this->assertTrue($this->logger->hasErrorThatContains('Websocket error processing topic callback function.'));
    }
}
