<?php

namespace TombaClub;
use \MediaWiki\MediaWikiServices;
use \MediaWiki\Registration\ExtensionRegistry;
use \MediaWiki\Revision\SlotRecord;
use \Wikimedia\Rdbms\SelectQueryBuilder;
use \ParserOptions;
use \ObjectCache;
use \Title;
use \RequestContext;
use \DOMDocument;
use \DOMXPath;

class WikiManager {
  /**
   * Returns a summary for a given article for use in the featured article section.
   */
  private static function makeFeaturedArticleSummary($title) {
    $article = Title::newFromText($title);
    $abstract = self::getArticleAbstract($article);
    return [
      'title' => $article->getPrefixedText(),
      'content' => $abstract,
    ];
  }

  /**
   * Returns the rendered HTML for a given article title.
   */
  private static function getArticleHTML($title) {
    $instance = MediaWikiServices::getInstance();

    $pageStore = $instance->getPageStore();
    $revisionStore = $instance->getRevisionStore();
    $pageFactory = $instance->getWikiPageFactory();
    $contentRenderer = $instance->getContentRenderer();

    $page = $pageFactory->newFromTitle($title);
    $rev = $revisionStore->getRevisionByTitle($title);
    $content = $rev->getContent(SlotRecord::MAIN);
    if (!$content) {
      return '';
    }
    
    $context = RequestContext::getMain();
    $options = ParserOptions::newFromUser($context->getUser());
    $options->setSuppressSectionEditLinks(true);

    $output = $contentRenderer->getParserOutput(
      $content,
      $page,
      null,
      $options,
    );

    $text = $output->getText();

    return $text;
  }

  /**
   * Returns the abstract of an article.
   */
  public static function getArticleAbstract($title) {
    $html = self::getArticleHTML($title);
    $dom = new DOMDocument();

    libxml_use_internal_errors(true);
    $dom->loadHTML('<!doctype html><html><meta charset="utf-8"><body>'.$html.'</body></html>');
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Everything relevant is in the .mw-parser-output div.
    $wrapperNodes = $xpath->query('//div[contains(@class, "mw-parser-output")]');
    if ($wrapperNodes->length === 0) {
      return '';
    }
    $wrapper = $wrapperNodes->item(0);

    // Remove the table of contents.
    foreach ($xpath->query('//div[@id="toc"]') as $tocDiv) {
      $tocDiv->parentNode->removeChild($tocDiv);
    }

    // Remove all thumbs and tright divs since they take up too much space.
    foreach ($xpath->query('//*[contains(@class, "thumb") or contains(@class, "tright")]') as $thumbDiv) {
      $thumbDiv->parentNode->removeChild($thumbDiv);
    }

    // Remove everything after the first heading (where the table of contents would normally start).
    // The remaining content is the article's abstract.
    $foundHeader = false;
    foreach (iterator_to_array($wrapper->childNodes) as $node) {
      if ($foundHeader) {
        $wrapper->removeChild($node);
        continue;
      }
      if ($node->nodeType === XML_ELEMENT_NODE) {
        $tag = strtolower($node->nodeName);
        if (in_array($tag, ['h2', 'h3', 'h4', 'h5', 'h6'], true)) {
          $foundHeader = true;
          $wrapper->removeChild($node);
        }
      }
    }

    // Convert back to an HTML string.
    $output = '';
    foreach ($wrapper->childNodes as $child) {
      $output .= $dom->saveHTML($child);
    }
    return trim($output);
  }
  
  /**
   * Returns a featured article for a given date.
   * 
   * The featured article is selected randomly and then cached.
   */
  public static function getFeaturedArticle($date) {
    // Cache key for the given date.
    $key = 'Main_Page_Featured_Article_'.$date;

    // Return the featured article from cache if we already have it.
    $value = Settings::getCacheValue($key);
    if ($value !== false) {
      return $value['article'];
    }

    // If we don't have a cached value yet, fetch one from the database and cache it.
    $featured = self::getRandomFeaturedArticle();
    $article = self::makeFeaturedArticleSummary($featured);
    $value = ['article' => $article];

    if (is_null($featured)) {
      // If no featured article could be found, cache this for a short time.
      // This allows us to try again without having to wait 24 hours.
      Settings::setCacheValue($key, $value, 3600);
    }
    else {
      // Otherwise, cache this for 72 hours. In reality we display a different one every day.
      Settings::setCacheValue($key, $value, 3600 * 72);
    }

    return $value['article'];
  }

  /**
   * Returns the latest post IDs added to the imageboard.
   */
  public static function getLatestImageboardPostData() {
    $db = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
    
    $query = $db->newSelectQueryBuilder()
      ->select([
        'p.id',
        'p.page_id',
        'p.created_at',
        'pd.rating',
        'pd.preview_filename',
      ])
      ->from('tombooru_post', 'p')
      ->leftJoin('tombooru_post_data', 'pd', 'pd.id = p.id')
      ->where(['pd.rating' => 'safe'])
      ->where(['pd.status' => ['active', 'pending_approval']])
      ->orderBy('p.id', SelectQueryBuilder::SORT_DESC)
      ->limit(16)
      ->caller(__METHOD__);

    $res = $query->fetchResultSet();
    $posts = [];
    foreach ($res as $row) {
      $row = (array)$row;
      $posts[] = $row;
    }

    return $posts;
  }

  /**
   * Returns a URL to an imageboard post.
   */
  private static function getImageboardPostLink($pageID) {
    $base = Settings::config()->get('TombooruBasePath');
    return str_replace('$1', 'posts/view/'.intval($pageID), $base);
  }

  /**
   * Returns file instances for an imageboard post's main file and preview.
   */
  private static function getFileInstances($pageID, $previewFilename) {
    $repo = MediaWikiServices::getInstance()->getRepoGroup();

    $file = null;
    $preview = null;

    if (!empty($previewFilename)) {
      $title = Title::makeTitleSafe(\NS_FILE, $previewFilename);
      $preview = $repo->findFile($title);
    }

    $title = Title::newFromID($pageID, \NS_FILE);
    $file = $repo->findFile($title);

    return ['file' => $file, 'preview' => $preview];
  }

  /**
   * Returns file data for an imageboard post.
   */
  private static function getFileData($fileInstance, $previewInstance) {
    $previewInstance = !empty($previewInstance) ? $previewFileInstance : $fileInstance;

    $originalWidth = $fileInstance->getWidth();
    $originalHeight = $fileInstance->getHeight();
    
    // For video files, we don't have width/height. Use the preview size in that case.
    if ($originalWidth === 0 || $originalHeight === 0) {
      $originalWidth = $previewInstance->getWidth();
      $originalHeight = $previewInstance->getHeight();
    }

    $thumb = $previewInstance->transform(['width' => 300]);
    
    return [
      'name' => $fileInstance->getName(),
      'mime' => $fileInstance->getMimeType(),
      'size' => $fileInstance->getSize(),
      'media' => [
        'thumb' => [
          'url' => $thumb->getUrl(),
          'width' => $thumb->getWidth(),
          'height' => $thumb->getHeight(),
        ],
      ],
    ];
  }

  /**
   * Returns the latest posts added to the imageboard.
   */
  public static function getLatestImageboardPosts() {
    $postData = self::getLatestImageboardPostData();
    $posts = [];
    foreach ($postData as $post) {
      $files = self::getFileInstances($post['page_id'], $post['preview_filename']);
      $fileData = self::getFileData($files['file'], $files['preview']);
      $link = self::getImageboardPostLink($post['page_id']);
      $posts[] = [...$post, 'link' => $link, 'file' => $fileData];
    }
    return $posts;
  }

  /**
   * Returns a random featured article from the database.
   * 
   * Any article that has the "Featured_articles" category is considered.
   * 
   * If no article is found, null is returned.
   */
  public static function getRandomFeaturedArticle() {
    $db = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

    $query = $db->newSelectQueryBuilder()
      ->from('categorylinks')
      ->join('page', null, 'cl_from = page_id')
      ->fields(['page_namespace', 'page_title'])
      ->where([
        'cl_to' => 'Featured_articles',
        'page_namespace' => NS_MAIN,
      ])
      ->orderBy('rand()')
      ->limit(1)
      ->caller(__METHOD__);
    
    $row = $query->fetchRow();

    if ($row) {
      $titleText = Title::makeTitle($row->page_namespace, $row->page_title)->getPrefixedText();
      return $titleText;
    }

    return null;
  }

  /**
   * Returns parent categories for a given set of category page IDs.
   */
  private static function getParentCategories($categoryPageIDs) {
    $dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

    if (empty($categoryPageIDs)) {
      return [];
    }

    $query = $dbr->newSelectQueryBuilder()
      ->select([
        'child_id' => 'cl.cl_from',
        'parent_dbkey' => 'cl.cl_to',
        'parent_id' => 'p.page_id',
      ])
      ->from('categorylinks', 'cl')
      ->leftJoin('page', 'p', 'cl.cl_to = p.page_title')
      ->where([
        'cl.cl_from' => $categoryPageIDs,
        'p.page_namespace' => NS_CATEGORY,
      ])
      ->caller(__METHOD__);
    
    $res = $query->fetchResultSet();

    $parents = [];
    $parentIDs = [];
    $childIDs = [];

    foreach ($res as $row) {
      $childID = (int)$row->child_id;
      $parentDbKey = $row->parent_dbkey;
      $parentID = (int)$row->parent_id;

      if (isset($parents[$parentID])) {
        continue;
      }
      $parentIDs[] = $parentID;
      $childIDs[] = $childID;
      $parents[$parentID] = [
        'id' => $parentID,
        'title' => $parentDbKey,
        'childID' => $childID,
      ];

      // if (isset($parents[$childID])) {
      //   continue;
      // }
      // $parentIDs[] = $parentID;
      // $parents[$childID] = [
      //   'id' => $parentID,
      //   'title' => $parentDbKey,
      // ];
    }

    return [$parents, array_unique($parentIDs), array_unique($childIDs)];
  }

  /**
   * Returns category data about a given article.
   * 
   * This first lists all categories for the article, and then goes 3 levels deep
   * getting the category parents for those categories (if any).
   */
  public static function getArticleCategoryData($articleID) {
    $dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

    $data = [
      'articleID' => $articleID,
      'categoryIDs' => [],
    ];
    $categories = [];

    $level = 0;

    $currentLevelIDs = [$articleID];
    $categoryPageIDs = [];

    while ($level <= 3 && !empty($currentLevelIDs)) {
      [$parents, $parentIDs, $childIDs] = self::getParentCategories($currentLevelIDs);

      if ($level === 0) {
        $data['categoryIDs'] = $parentIDs;
      }

      foreach ($parents as $parentID => $data) {
        $categories[$parentID] = $data;
      }

      $level = $level + 1;
      $currentLevelIDs = $parentIDs;
    }

    return [$data, $categories];
  }
}
