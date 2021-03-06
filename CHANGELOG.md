# Changelog

## 3.4.0 (2020-??-??)

- Deprecated support for the `gos/websocket-client` package, use `ratchet/pawl` instead
- Deprecated the `Gos\Bundle\WebSocketBundle\Client\Driver\DoctrineCacheDriverDecorator`, if using the `doctrine/cache` package a `Gos\Bundle\WebSocketBundle\Client\Driver\SymfonyCacheDriverDecorator` using a `Symfony\Component\Cache\DoctrineProvider` instance can be used

## 3.3.0 (2020-07-06)

- Change `Gos\Bundle\WebSocketBundle\Periodic\DoctrinePeriodicPing` to address the deprecation of `Doctrine\DBAL\Driver\PingableConnection`
- Deprecate support for `Doctrine\DBAL\Driver\PingableConnection` implementations in `Gos\Bundle\WebSocketBundle\Periodic\DoctrinePeriodicPing`, in 4.0 `Doctrine\DBAL\Connection` instances will be required
- Add aliases to bundle events to allow registering listeners using the FQCN
- Deprecated `Gos\Bundle\WebSocketBundle\Event\ClientErrorEvent::setException()`, in 4.0 a `Throwable` instance will be a required constructor argument
- Deprecated `Gos\Bundle\WebSocketBundle\Event\ClientErrorEvent::getException()`, use `Gos\Bundle\WebSocketBundle\Event\ClientErrorEvent::getThrowable()` instead
- Remove call to `Ratchet\Wamp\Topic::broadcast()` if the dispatch method fails, see [the security advisory](https://github.com/GeniusesOfSymfony/WebSocketBundle/security/advisories/GHSA-wwgf-3xp7-cxj4) for additional details

## 3.2.0 (2020-06-01)

- Extend `Gos\Component\WebSocketClient\Wamp\ClientFactoryInterface` inside `Gos\Bundle\WebSocketBundle\Pusher\Wamp\WampConnectionFactoryInterface`
- Added new `gos_web_socket.websocket_client` configuration node to configure a `Gos\Component\WebSocketClient\Wamp\ClientInterface` instance

## 3.1.0 (2020-05-31)

- Use the `symfony/deprecation-contracts` package to trigger runtime deprecation notices
- Deprecated `Gos\Bundle\WebSocketBundle\Pusher\PusherInterface` and `Gos\Bundle\WebSocketBundle\Pusher\ServerPushHandlerInterface`, and all related services, in favor of the Symfony Messenger component
- Removed `Gos\Bundle\WebSocketBundle\Client\ClientStorageInterface::setStorageDriver()`, this method should no longer be relied on
- [MINOR B/C BREAK] Changed the (final) `Gos\Bundle\WebSocketBundle\Client\ClientStorage` constructor to require a `Gos\Bundle\WebSocketBundle\Client\Driver\DriverInterface` instance as the first argument, this only affects users manually instantiating an instance of the storage class
- Deprecated unused `gos_web_socket.client.storage.prefix` configuration node and container parameter
- Address deprecations in marking configuration nodes, services, and service aliases deprecated in Symfony 5.1

## 3.0.0 (2020-04-02)

- Consult the UPGRADE guide for changes between 2.x and 3.0
