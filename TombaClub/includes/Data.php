<?php

namespace TombaClub;
use \MediaWiki\MediaWikiServices;
use \MediaWiki\Registration\ExtensionRegistry;
use \MediaWiki\Revision\SlotRecord;
use \MediaWiki\Title\Title;
use \ParserOptions;
use \ObjectCache;
use \RequestContext;
use \DOMDocument;
use \DOMXPath;

class Data {
  /**
   * Returns the contents of a cached JSON file.
   * 
   * The file is expected to have "updated" and "ttl" values to determine freshness.
   */
  private static function readCacheJSON($name) {
    $path = __DIR__.'/../cache/'.$name;
    if (!file_exists($path)) {
      return self::triggerCacheUpdate(false);
    }
    $data = json_decode(file_get_contents($path), true);
    self::triggerCacheUpdate($data);
    return $data;
  }

  /**
   * Queues a cache update if the cache is stale.
   * 
   * If the cache is still fresh, nothing happens.
   */
  private static function triggerCacheUpdate($data) {
    if (!self::isCacheStale($data)) {
      return;
    }
    // Trigger a cache update. This runs the script in the background without blocking.
    // We'll have the cache ready next time we run this code.
    $cmd = 'php '.escapeshellarg(realpath(__DIR__.'/../tc-data.php')).' > /dev/null 2>&1 &';
    exec($cmd);
  }

  /**
   * Returns whether the cache is stale or not.
   * 
   * If the cache is nonexistent, the cache is reported as stale.
   */
  private static function isCacheStale($data) {
    if ($data === false || !isset($data['updated']) || !isset($data['ttl'])) {
      return true;
    }
    $updated = strtotime($data['updated']);
    $ttl = intval($data['ttl']);
    if (time() - $updated > $ttl) {
      return true;
    }
    return false;
  }

  /**
   * Returns cached Youtube channel data.
   */
  public static function getCachedVideos() {
    $data = self::readCacheJSON('videos.json');
    if (empty($data)) {
      return null;
    }
    return $data;
  }

  /**
   * Returns cached Discord server data.
   */
  public static function getCachedDiscordInfo() {
    $data = self::readCacheJSON('discord.json');
    if (empty($data)) {
      return null;
    }
    return $data;
  }

  /**
   * Returns cached tweets data.
   */
  public static function getCachedTweets() {
    $data = self::readCacheJSON('tweets.json');
    if (empty($data)) {
      return null;
    }
    return $data;
  }
}
