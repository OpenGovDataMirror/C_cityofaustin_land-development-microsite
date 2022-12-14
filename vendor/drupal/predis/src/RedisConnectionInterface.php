<?php
/**
 * @file
 * Contains \Drupal\predis\RedisConnectionInterface.
 */

namespace Drupal\predis;

interface RedisConnectionInterface
{
  /**
   * Return Redis connection
   * @return [type] [description]
   */
  public function getConnection();

}
