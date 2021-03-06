<?php

namespace Mix\Etcd;

use Etcd\Client;
use Mix\Bean\BeanInjector;
use Mix\Etcd\Register\Registrar;
use Mix\Etcd\Service\ServiceMonitor;
use Mix\ServiceCenter\DialerInterface;
use Mix\ServiceCenter\ServiceBundleInterface;
use Mix\ServiceCenter\ServiceCenterInterface;
use Mix\ServiceCenter\ServiceInterface;

/**
 * Class ServiceCenter
 * @package Mix\Etcd
 */
class ServiceCenter implements ServiceCenterInterface
{

    /**
     * Host
     * @var string
     */
    public $host = '127.0.0.1';

    /**
     * Port
     * @var int
     */
    public $port = 2379;

    /**
     * Version
     * @var string
     */
    public $version = 'v3';

    /**
     * TTL
     * @var int
     */
    public $ttl = 5;

    /**
     * 拨号器
     * @var DialerInterface
     */
    public $dialer;

    /**
     * 注册器集合
     * @var Registrar[]
     */
    protected $registrars = [];

    /**
     * 服务监控集合
     * @var ServiceMonitor[]
     */
    protected $monitors = [];

    /**
     * ServiceCenter constructor.
     * @param array $config
     * @throws \PhpDocReader\AnnotationException
     * @throws \ReflectionException
     */
    public function __construct(array $config)
    {
        BeanInjector::inject($this, $config);
    }

    /**
     * Create Client
     * @return Client
     */
    protected function createClient()
    {
        return new Client(sprintf('%s:%d', $this->host, $this->port), $this->version);
    }

    /**
     * dial return connection
     * @param ServiceInterface $service
     * @return object
     */
    public function dial(ServiceInterface $service)
    {
        return $this->dialer->dial($service);
    }

    /**
     * Get Service
     * @param string $name
     * @return ServiceInterface
     */
    public function get(string $name): ServiceInterface
    {
        if (isset($this->monitors[$name])) {
            return $this->monitors[$name]->random();
        }
        $monitor               = new ServiceMonitor($this->createClient(), $name, $this->ttl);
        $this->monitors[$name] = $monitor;
        return $monitor->random();
    }

    /**
     * Register
     * @param ServiceBundleInterface $bundle
     * @throws \Exception
     */
    public function register(ServiceBundleInterface $bundle)
    {
        $id = spl_object_hash($bundle);
        if (isset($this->registrars[$id])) {
            throw new \Exception(sprintf('Repeat register service, bundle id: %s', $id));
        }
        $registrar = new Registrar($this->createClient(), $bundle, $this->ttl);
        $registrar->register();
        $this->registrars[$id] = $registrar;
    }

    /**
     * Un Register
     * @param ServiceBundleInterface $bundle
     * @throws \Exception
     */
    public function unregister(ServiceBundleInterface $bundle)
    {
        $id = spl_object_hash($bundle);
        if (!isset($this->registrars[$id])) {
            throw new \Exception(sprintf('Unregister service failed, bundle id: %s', $id));
        }
        $this->registrars[$id]->unregister();
        unset($this->registrars[$id]);
    }

    /**
     * Clear
     * close all monitor
     * unregister all service
     */
    public function clear()
    {
        foreach ($this->monitors as $monitor) {
            $monitor->close();
        }
        foreach ($this->registrars as $registrar) {
            $registrar->unregister();
        }
    }

}
