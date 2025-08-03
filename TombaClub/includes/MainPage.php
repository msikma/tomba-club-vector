<?php

namespace TombaClub;
use \MWTimestamp;
use \MediaWiki\MediaWikiServices;
use \Title;

class MainPage {
  public static function getImageboardPosts() {
    $buffer = [];
    $posts = WikiManager::getLatestImageboardPosts();
    foreach ($posts as $post) {
      $pageID = intval($post['page_id']);
      $thumb = @$post['file']['media']['thumb'];
      $thumbURL = @$thumb['url'];
      $width = @$thumb['width'];
      $height = @$thumb['height'];
      $link = $post['link'];
      $buffer[] = '<span><a href="'.htmlspecialchars($link).'"><img src="'.htmlspecialchars($thumbURL).'" width="'.intval($width).'" height="'.intval($height).'"></a></span>';
    }
    return implode(" ", $buffer);
  }
  private static function getDuration($seconds) {
    $duration = gmdate($seconds >= 3600 ? 'G:i:s' : 'i:s', intval($seconds));
    if (preg_match('/^0[0-9]:/', $duration)) {
      $duration = substr($duration, 1);
    }
    return $duration;
  }
  public static function getYoutubeVideos() {
    $buffer = [];
    $data = Data::getCachedVideos();
    $videos = @$data['data']['videos'];
    if (empty($data) || empty($videos)) {
      return '';
    }
    $used = 0;
    foreach ($videos as $video) {
      if ($used >= 3) {
        break;
      }
      $id = $video['id'];
      $link = $video['url'];
      $title = $video['title'];
      $duration = self::getDuration($video['duration']);
      $thumbnail = $video['thumbnail'];
      $published = self::getPostTimestamp($video['published']);

      $buffer[] = '
        <li>
          <a href="'.htmlspecialchars($link).'">
            <span class="preview">
              <span class="thumb">
                <img src="'.htmlspecialchars($thumbnail['url']).'" width="'.intval($thumbnail['width']).'" height="'.intval($thumbnail['height']).'">
                <span class="duration">'.htmlspecialchars($duration).'</span>
              </span>
            </span>
            <span class="info">
              <span class="title">'.htmlspecialchars($title).'</span>
              <span class="footer date">'.htmlspecialchars($published).'</span>
            </span>
          </a>
        </li>
      ';
      $used += 1;
    }
    return '<ul class="topics youtube social-posts">'.implode("\n", $buffer).'</ul>';
  }
  private static function getTweetContentLine($line) {
    $line = htmlspecialchars($line);
    $line = preg_replace('/@(\w+)/u', '<em>@$1</em>', $line);
    return trim($line);
  }
  private static function getTweetContent($content, $replyingTo) {
    $buffer = [];
    if (!empty($replyingTo)) {
      $content = implode(' ', $replyingTo).' '.$content;
    }
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
      $buffer[] = '<p>'.self::getTweetContentLine($line).'</p>';
    }
    return implode("\n", $buffer);
  }
  private static function getTweetAttachments($attachments) {
    if (empty($attachments)) {
      return '';
    }
    $buffer = [];
    foreach ($attachments as $attachment) {
      $alt = $attachment['altText'];
      $src = $attachment['thumbnailURL'];
      $buffer[] = '<div class="attachment"><img src="'.htmlspecialchars($src, ENT_QUOTES).'" alt="'.htmlspecialchars($alt, ENT_QUOTES,).'" /></div>';
      break;
    }
    return implode("\n", $buffer);
  }
  private static function getPostTimestamp($date) {
    if (empty($date)) {
      return 'Unknown date';
    }
    $instance = MediaWikiServices::getInstance();
    $user = Settings::getUser();
    $option = $instance->getUserOptionsLookup()->getOption($user, 'language');
    $language = $instance->getLanguageFactory()->getLanguage($option);
    $ts = wfTimestamp(TS_MW, $date);
    $timestamp = MWTimestamp::getInstance($ts);

    return $language->userDate($timestamp, $user);
  }
  public static function getTwitterPosts() {
    $buffer = [];
    $tweets = Data::getCachedTweets();
    if (empty($tweets)) {
      return '';
    }
    $data = $tweets['data'];
    $used = 0;
    for ($n = 0; $n < count($data['tweets']); ++$n) {
      $tweet = $data['tweets'][$n];
      if ($used >= 3) {
        break;
      }
      if (empty($tweet['content'])) {
        continue;
      }
      if (!empty($tweet['replyingTo'])) {
        continue;
      }
      $id = $tweet['id'];
      $username = $tweet['username'];
      $unix = strtotime($tweet['date']);
      $avatar = $tweet['avatar'];
      $timestamp = self::getPostTimestamp($tweet['date']);
      $content = self::getTweetContent($tweet['content'], $tweet['replyingTo']);
      $attachments = self::getTweetAttachments($tweet['attachments']);
      $stats = $tweet['stats'];
      $isByTombaClub = $username === '@TombaClub';
      $buffer[] = '
        <li>
          <div class="container" data-tweet-id="'.htmlspecialchars($id).'">
            '.(!$isByTombaClub ? '<div class="meta-header rt"><span>Tomba Club reposted</span></div>' : '').'
            <div class="main">
              <div class="user">
                <div class="thumb'.($isByTombaClub ? ' tomba-club' : '').'"><img src="'.$avatar.'" width="80" height="80" /></div>
              </div>
              <div class="info">
                <div class="tweet-header">
                  <div class="name">'.htmlspecialchars($username).'</div>
                  <div class="separator"> · </div>
                  <div class="timestamp"><a href="'.htmlspecialchars($tweet['url']).'" class="date">'.$timestamp.'</a></div>
                </div>
                <div class="description">
                  '.$content.'
                </div>
                <div class="footer">
                  <div class="meta"><span class="stat replies">'.intval($stats['comments']).'</span><span class="stat rts">'.intval($stats['retweets']).'</span><span class="stat likes">'.intval($stats['likes']).'</span></div>
                </div>
              </div>
              '.(!empty($attachments) ? '<div class="attachments">'.$attachments.'</div>' : '').'
            </div>
          </div>
        </li>
      ';
      $used += 1;
    }

    return '
      <ul class="topics social-posts tweets">'.implode("\n", $buffer).'</ul>
    ';
  }
  public static function getFeaturedArticle() {
    $article = WikiManager::getFeaturedArticle(date('Y-m-d'));
    $title = \Title::newFromText($article['title']);
    $heading = ArticleMeta::generateArticleHeading($title, [], true);

    if (empty($article)) {
      return '
        <div class="placeholder no-featured-article">
          <p>No featured article for today. Check back tomorrow.</p>
        </div>
      ';
    }
    return '
      <div class="article portal-history">
        '.(empty($heading) ? ('<h2>'.htmlspecialchars($article['title']).'</h2>') : ($heading)).'
        '.$article['content'].'
      </div>
    ';
  }
  public static function getDiscordOnlineUsers() {
    $discord = Data::getCachedDiscordInfo();
    if (empty($discord)) {
      return '';
    }
    $data = $discord['data'];
    $presence = $data['presence_count'];
    return '<span>'.intval($presence).' online</span>';
  }
}
