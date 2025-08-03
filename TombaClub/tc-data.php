<?php
// This script does NOT run from within the MediaWiki environment.
//
// Basically, this is a "fire and forget" script that caches a bunch of
// external data as needed. This can be pinged every once in a while and
// only fetches new data if it's stale.
//
// The TombaClub/includes/Data.php file pings this script.

$env = @include(file_exists(__DIR__.'/env.php') ? __DIR__.'/env.php' : __DIR__.'/env.example.php');

$tasks = [
  'videos' => [
    'ttl' => 7200,
    'file' => 'videos.json',
    'fetch' => function () use ($env) {
      function getBestThumbnail($thumbnails) {
        usort($thumbnails, function ($a, $b) {
          return $b['width'] <=> $a['width'];
        });
        return reset($thumbnails);
      }

      function getChannelRSS($id) {
        $url = "https://www.youtube.com/feeds/videos.xml?channel_id={$id}";
        $rss = @file_get_contents($url);
        if ($rss === false) {
          return null;
        }
        $xml = @simplexml_load_string($rss);
        if ($xml === false) {
          return null;
        }
        return $xml;
      }

      function getEntryFromRSS($id, $rss) {
        foreach ($rss->entry as $entry) {
          if ((string)$entry->id === "yt:video:{$id}") {
            return $entry;
          }
        }
        return null;
      }

      $ytdlp = !empty($env['yt-dlp']) ? $env['yt-dlp'] : 'yt-dlp';
      $cmd = 'command -v '.$ytdlp.' >/dev/null 2>&1 || exit 0; '.$ytdlp.' --flat-playlist --skip-download --dump-single-json --playlist-end 10 "https://www.youtube.com/@TombaClub/videos" 2> /dev/null';
      $output = shell_exec($cmd);
      if ($output === null) {
        throw new Error('no yt-dlp: '.$ytdlp);
      }
      $data = json_decode($output, true);
      $rss = getChannelRSS($data['id']);
      if ($rss === null) {
        throw new Error('cannot get rss: "'.$data['id'].'"');
      }

      $videos = array_map(function($entry) use ($rss) {
        try {
          $rssData = getEntryFromRSS($entry['id'], $rss);
          if (empty($rssData)) {
            return null;
          }
          $data = [
            'id' => $entry['id'],
            'url' => $entry['url'],
            'title' => $entry['title'],
            'description' => $entry['description'],
            'duration' => intval($entry['duration']),
            'thumbnail' => getBestThumbnail($entry['thumbnails']),
            'published' => (string)$rssData->published,
            'views' => intval($entry['view_count']),
          ];
          return $data;
        }
        catch (Throwable $e) {
          return null;
        }
      }, $data['entries']);

      return [
        'id' => $data['id'],
        'title' => $data['channel'],
        'description' => $data['description'],
        'name' => $data['uploader_id'],
        'link' => $data['uploader_url'],
        'videos' => array_filter($videos),
        'subscribers' => $data['channel_follower_count'],
      ];
      return $data;
    }
  ],

  'discord' => [
    'ttl' => 300,
    'file' => 'discord.json',
    'fetch' => function () use ($env) {
      $url = "https://discord.com/api/guilds/{$env['discordGuildID']}/widget.json";
      $data = file_get_contents($url);
      $data = json_decode($data, true);
      if (!empty($data['members'])) {
        unset($data['members']);
      }
      return $data;
    }
  ],

  'tweets' => [
    'ttl' => 3600,
    'file' => 'tweets.json',
    'fetch' => function ($existingData) {
      function fetchAsBrowser($url) {
        $ch = curl_init();

        $headers = [
          'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
          'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
          'Accept-Language: en-US,en;q=0.5',
          'Accept-Encoding: gzip, deflate, br, zstd',
          'Alt-Used: nitter.net',
          'Connection: keep-alive',
          'Upgrade-Insecure-Requests: 1',
          'Sec-Fetch-Dest: document',
          'Sec-Fetch-Mode: navigate',
          'Sec-Fetch-Site: same-origin',
          'Priority: u=0, i',
          'TE: trailers'
        ];

        curl_setopt_array($ch, [
          CURLOPT_URL => $url,
          CURLOPT_HTTPHEADER => $headers,
          CURLOPT_ENCODING => '',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
      }

      function getFirstNodeContent(DOMXPath $xpath, DOMNode $contextNode, $query) {
        $nodes = $xpath->query($query, $contextNode);
        return $nodes->length > 0 ? trim($nodes[0]->textContent) : '';
      }

      function getFirstNodeAttribute(DOMXPath $xpath, DOMNode $contextNode, $query, $attribute) {
        $nodes = $xpath->query($query, $contextNode);
        return $nodes->length > 0 ? trim($nodes[0]->getAttribute($attribute)) : '';
      }

      function getNodesArray(DOMXPath $xpath, DOMNode $contextNode, $query) {
        $result = [];
        $nodes = $xpath->query($query, $contextNode);
        foreach ($nodes as $node) {
          $result[] = trim($node->textContent);
        }
        return $result;
      }

      function parseTweetStats(DOMXPath $xpath, DOMNode $tweetNode) {
        $stats = [
          'comments' => 0,
          'retweets' => 0,
          'quotes' => 0,
          'likes' => 0
        ];
        
        $statNodes = $xpath->query(".//span[contains(@class, 'tweet-stat')]", $tweetNode);

        foreach ($statNodes as $stat) {
          $html = $stat->ownerDocument->saveHTML($stat);
          
          if (strpos($html, 'icon-comment') !== false) {
            $stats['comments'] = (int)preg_replace('/[^0-9]/', '', $stat->textContent);
          }
          elseif (strpos($html, 'icon-retweet') !== false) {
            $stats['retweets'] = (int)preg_replace('/[^0-9]/', '', $stat->textContent);
          }
          elseif (strpos($html, 'icon-quote') !== false) {
            $stats['quotes'] = (int)preg_replace('/[^0-9]/', '', $stat->textContent);
          }
          elseif (strpos($html, 'icon-heart') !== false) {
            $stats['likes'] = (int)preg_replace('/[^0-9]/', '', $stat->textContent);
          }
        }
        
        return $stats;
      }

      function convertNitterAvatarUrl($nitterURL) {
        if (strpos($nitterURL, 'http') === 0) {
          return $nitterURL;
        }

        $decodedPath = urldecode(ltrim($nitterURL, '/'));
        if (strpos($decodedPath, 'pic/') === 0) {
          $decodedPath = substr($decodedPath, 4);
        }
        $parts = explode('/', $decodedPath);
        $path = implode('/', $parts);
        
        return 'https://pbs.twimg.com/'.$path;
      }

      function convertNitterTimestamp($nitterDate) {
        $cleanDate = str_replace('· ', '', $nitterDate);
        if (preg_match('/([A-Za-z]+) (\d{1,2}), (\d{4}) (\d{1,2}):(\d{2}) ([AP]M) UTC/', $cleanDate, $matches)) {
          $month = $matches[1];
          $day = $matches[2];
          $year = $matches[3];
          $hour = $matches[4];
          $minute = $matches[5];
          $ampm = $matches[6];
          
          $hour = ($ampm == 'PM' && $hour < 12) ? $hour + 12 : $hour;
          $hour = ($ampm == 'AM' && $hour == 12) ? 0 : $hour;
          
          $dateStr = sprintf('%s %d %d %02d:%02d:00 UTC', $month, $day, $year, $hour, $minute);
        }
        else {
          return null;
        }
        
        $dateTime = DateTime::createFromFormat('M j Y H:i:s e', $dateStr);
        
        return $dateTime ? $dateTime->format('c') : null;
      }

      function convertNitterImageUrl($nitterUrl) {
        if (strpos($nitterUrl, 'http') === 0) {
          return $nitterUrl;
        }

        $decodedPath = urldecode(ltrim($nitterUrl, '/'));
        
        if (strpos($decodedPath, 'pic/media') === 0) {
          $path = str_replace('pic/media', 'media', $decodedPath);
          $path = preg_replace('/\?.*$/', '', $path);
          return 'https://pbs.twimg.com/'.$path;
        }
        elseif (strpos($decodedPath, 'pic/orig/media') === 0) {
          $path = str_replace('pic/orig/media', 'media', $decodedPath);
          return 'https://pbs.twimg.com/'.$path;
        }
        
        return 'https://nitter.net'.$nitterUrl;
      }

      function extractTweetAttachments($xpath, $tweetNode) {
        $attachments = [];
        $imageLinks = $xpath->query(".//div[contains(@class, 'attachments')]//a[contains(@class, 'still-image')]", $tweetNode);
        
        foreach ($imageLinks as $link) {
          $href = $link->getAttribute('href');
          $img = $xpath->query(".//img", $link)->item(0);
          
          if ($img) {
            $attachments[] = [
              'originalURL' => convertNitterImageUrl($href),
              'thumbnailURL' => convertNitterImageUrl($img->getAttribute('src')),
              'altText' => $img->getAttribute('alt') ?: ''
            ];
          }
        }
        
        return $attachments;
      }

      function getTweetID($url) {
        if (preg_match('~/status/(\d+)~', $url, $matches)) {
          return $matches[1];
        }
        return null;
      }

      function getAbsoluteURL($relURL = '') {
        $url = 'https://twitter.com'.$relURL;
        $url = preg_replace('/#.*$/', '', $url);
        return $url;
      }

      $url = 'https://nitter.net/search?f=tweets&q=%28from%3A%40TombaClub%29&since=&until=&near=';
      $html = fetchAsBrowser($url);
      if (!$html) {
        throw new Error('Could not fetch html (empty response)');
      }

      $dom = new DOMDocument();
      libxml_use_internal_errors(true);
      $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
      libxml_clear_errors();
      $xpath = new DOMXPath($dom);
      $items = $xpath->query("//div[contains(@class, 'timeline')]/div[contains(@class, 'timeline-item')]");
      $tweets = [];
      
      foreach ($items as $item) {
        $tweetUrl = getAbsoluteURL(getFirstNodeAttribute($xpath, $item, ".//a[contains(@class, 'tweet-link')]", 'href'));
        $avatarUrl = convertNitterAvatarUrl(getFirstNodeAttribute($xpath, $item, ".//img[contains(@class, 'avatar')]", 'src'));
        $id = getTweetID($tweetUrl);
        
        $tweet = [
          'id' => $id,
          'url' => $tweetUrl,
          'avatar' => $avatarUrl,
          'fullname' => getFirstNodeContent($xpath, $item, ".//a[contains(@class, 'fullname')]"),
          'username' => getFirstNodeContent($xpath, $item, ".//a[contains(@class, 'username')]"),
          'date' => convertNitterTimestamp(getFirstNodeAttribute($xpath, $item, ".//span[contains(@class, 'tweet-date')]/a", 'title')),
          'replyingTo' => getNodesArray($xpath, $item, ".//div[contains(@class, 'replying-to')]/a[starts-with(text(), '@')]"),
          'attachments' => extractTweetAttachments($xpath, $item),
          'content' => getFirstNodeContent($xpath, $item, ".//div[contains(@class, 'tweet-content')]"),
          'stats' => parseTweetStats($xpath, $item)
        ];

        if (empty($tweet['date'])) {
          throw new Error('Unable to extract tweet data from html');
        }

        $tweets[$id] = $tweet;
      }

      if (!empty($existingData['tweets'])) {
        foreach (@$existingData['tweets'] as $tweet) {
          $tweets[$tweet['id']] = $tweet;
        }
      }

      usort($tweets, function($a, $b) {
        return strtotime($b['date']) <=> strtotime($a['date']);
      });

      $tweets = array_slice(array_values($tweets), 0, 50);

      return [
        'name' => 'TombaClub',
        'link' => 'https://twitter.com/TombaClub/',
        'tweets' => $tweets,
      ];
    }
  ],
];

/**
 * Ensures that the cache directory exists.
 */
function ensureCacheDir() {
  $cacheDir = getCacheDir();
  if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
  }
}

/**
 * Returns the cache directory.
 */
function getCacheDir() {
  return __DIR__.'/cache';
}

/**
 * Checks if we should be running a given task.
 */
function shouldFetch($file, $ttl, $status) {
  if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
    return false;
  }

  if (file_exists($status) && (time() - filemtime($status)) < $ttl) {
    return false;
  }

  return true;
}

/**
 * Returns the existing data, or an empty array if none.
 */
function getExistingData($file) {
  if (!file_exists($file)) {
    return [];
  }
  try {
    $data = json_decode(file_get_contents($file), true);
    return $data;
  }
  catch (Throwable $e) {
    return [];
  }
}

/**
 * Runs all tasks as needed.
 */
function runTasks($tasks) {
  ensureCacheDir();
  $cacheDir = getCacheDir();

  foreach ($tasks as $name => $task) {
    $ttl = $task['ttl'];
    $file = "$cacheDir/{$task['file']}";
    $fetch = $task['fetch'];
    $status = "$cacheDir/{$name}_status.txt";
    $error = null;
    $updated = date('c');

    if (!shouldFetch($file, $ttl, $status)) {
      continue;
    }

    $existing = getExistingData($file);

    file_put_contents($status, 'fetching');

    try {
      $data = $fetch(@$existing['data']);
    }
    catch (Throwable $e) {
      $data = null;
      $error = $e->getMessage();
    }

    if ($data === null) {
      $data = @$existing['data'];
      $updated = @$existing['updated'];
    }

    file_put_contents(
      $file,
      json_encode(
        [
          'task' => $name,
          'updated' => $updated,
          'ttl' => $ttl,
          'run' => date('c'),
          'data' => $data,
          'error' => $error,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
      ),
    );

    unlink($status);
  }
}

runTasks($tasks);
