<?php
/**
 * @file
 * Contains Drupal\predis\Database\Predis.
 */
namespace Drupal\predis\Database;

use Predis\Client;
use Drupal\predis\RedisConnectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

class Predis implements RedisConnectionInterface
{
  /**
   * @var Predis\Client
   */
  private $redis;

  /**
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config_factory;

  /**
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct(ConfigFactoryInterface $config_factory)
  {
    $this->config_factory = $config_factory;
  }

  protected function makeRedisConnection($config)
  {
    $config += $this->defaultOptions();
    $this->redis = new Client($config);
    return $this->redis;
  }

  /**
   * Default options
   * @return Array
   */
  protected function defaultOptions()
  {
    return [
      'scheme' => 'tcp',
      'host' => '127.0.0.1',
      'port' => 6379,
      'database' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  final public function getConnection()
  {
    if ($this->redis) {
      return $this->redis;
    }

    $settings = $this->config_factory->get('predis.settings');
    $config = $settings->get('connections');

    return $this->makeRedisConnection($config);
  }
}
