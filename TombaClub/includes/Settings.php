<?php

namespace TombaClub;
use \MediaWiki\MediaWikiServices;
use \MediaWiki\Registration\ExtensionRegistry;
use \RequestContext;
use \ObjectCache;

class Settings {
  private static $config = null;
  private static $user = null;

  /**
   * Returns the config object.
   */
  public static function config() {
    if (self::$config === null) {
      self::$config = MediaWikiServices::getInstance()->getMainConfig();
    }
    return self::$config;
  }

  /**
   * Returns the base URL; we use the ArticlePath and strip out the $1.
   */
  public static function getBaseURL() {
    $server = self::config()->get('Server');
    $articlePath = self::config()->get('ArticlePath');
    $baseURL = preg_replace('/\$1$/', '', $articlePath);
    return $server.$baseURL;
  }

  /**
   * Returns the base URL for Tombooru.
   * 
   * This is /imageboard/ instead of /wiki/.
   */
  public static function getTombooruBaseURL() {
    $baseURL = self::getBaseURL();
    $baseURL = preg_replace('/\/wiki\/$/', '/imageboard/', $baseURL);
    return $baseURL;
  }

  /**
   * Returns the currently logged in user.
   */
  public static function getUser() {
    if (!empty(self::$user)) {
      return self::$user;
    }
    $instance = MediaWikiServices::getInstance();
    $user = RequestContext::getMain()->getUser();
    self::$user = $user;
    return $user;
  }

  /**
   * Retrieves values from the cache.
   * 
   * If a value is not found, false is returned.
   */
  public static function getCacheValue($key) {
    if (empty($key)) {
      throw new \Exception('No cache key set.');
    }
    $cache = ObjectCache::getInstance(CACHE_DB);
    $cacheKey = $cache->makeKey('TombaClub', 'Data', $key);

    $data = $cache->get($cacheKey);
    if ($data !== false) {
      return json_decode($data, true);
    }

    return false;
  }

  /**
   * Stores a value in the cache.
   * 
   * Data is always JSON encoded.
   */
  public static function setCacheValue($key, $data, $time) {
    if (empty($key) || empty($time)) {
      throw new \Exception('No cache key or time set.');
    }
    $cache = ObjectCache::getInstance(CACHE_DB);
    $cacheKey = $cache->makeKey('TombaClub', 'Data', $key);
    $cache->set($cacheKey, json_encode($data), $time);

    return true;
  }

  /**
   * Returns the URL to the wiki's copyright license.
   */
  public static function getWikiLicenseURL() {
    return self::config()->get('RightsUrl');
  }

  /**
   * Returns the extension's base directory.
   */
  public static function getExtensionBaseDir() {
    return self::config()->get('ExtensionAssetsPath').'/TombaClub';
  }
}
