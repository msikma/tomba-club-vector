<?php

namespace TombaClub;
use \MediaWiki\MediaWikiServices;
use \MediaWiki\Title\Title;
use \RequestContext;

class ArticleMeta {
  // The main topics on Tomba Club Wiki and their internal names.
  private static $mainTopics = [
    'guidebook' => [
      'category' => 'Guidebook',
    ],
    'history' => [
      'category' => 'Behind_the_scenes',
    ],
    'media' => [
      'category' => 'Promotion_and_media'
    ],
    'cut' => [
      'category' => 'Cut_content',
    ],
    'technical' => [
      'category' => 'Technical_information',
    ],
    'community' => [
      'category' => 'Community_and_fandom',
    ],
  ];

  /**
   * Returns the main topic for a given category.
   * 
   * If no main topic is applicable, null is returned.
   */
  private static function getCategoryTopic($parentCategoryTitle) {
    if (empty($parentCategoryTitle)) {
      return null;
    }
    foreach (self::$mainTopics as $type => $data) {
      if ($data['category'] === $parentCategoryTitle) {
        return $type;
      }
    }
    return null;
  }

  /**
   * Generates a set of category links for an article heading.
   */
  private static function getArticleHeadingCategoryLinks($articleTitle, $categoryData) {
    $mainTopic = @$categoryData['mainTopic'];
    $mainTopicData = @self::$mainTopics[$mainTopic];
    if (empty($categoryData) || empty($mainTopicData)) {
      return '';
    }

    $mainCategory = Title::newFromText($mainTopicData['category']);

    $buffer = [];
    foreach ($categoryData['topicList'] as $category) {
      $isMainTopic = $mainTopicData['category'] === $category['title'];
      $class = $isMainTopic ? 'main-topic' : 'sub';
      $name = str_replace('_', ' ', $category['title']);

      if ($isMainTopic) {
        // For the main topic, we'll always link to the portal.
        $link = Title::newFromText($category['title'], NS_PORTAL);
      }
      else {
        // We either link to the main namespace page, if it exists,
        // or to the category page (which always exists).
        $link = Title::newFromText($category['title'], NS_MAIN);

        if (!$link || !$link->exists()) {
          $link = Title::newFromText($category['title'], NS_CATEGORY);
        }
      }

      $buffer[] = '<a class="category '.$class.'" href="'.htmlspecialchars($link->getLocalURL()).'">'.htmlspecialchars($name).'</a><span class="separator"></span>';
    }

    return '
      <div class="categories">'.implode(" ", $buffer).'</div>
    ';
  }

  /**
   * Finds a category in a hierarchy that denotes a main topic.
   */
  private static function findTopicCategory($categoryHierarchy) {
    foreach (array_values($categoryHierarchy) as $category) {
      $type = self::getCategoryTopic($category['title']);
      if (is_null($type)) {
        continue;
      }
      return [$type, $category['id']];
    }
    return [null, null];
  }

  /**
   * Returns article category data for use in the article heading section.
   * 
   * This retrieves all known categories for the image and their parent categories,
   * and checks to see if any of the parent categories are in the list of main topics.
   * If this article has a category that belongs to a main topic, we reflect it in the
   * design of the article heading.
   * 
   * This also receives the parsed category titles from the ParserOutput, which may include
   * categories that don't have a page yet (and thus won't be in the database).
   * 
   * If $includeNonexistentCategories is set, categories without a corresponding
   * database entry will not be included.
   */
  private static function getArticleHeadingCategoryData($id, $parsedCategoryTitles, $includeNonexistentCategories = false) {
    // Fetch the article's categories (at least, all that have a page) and their parent categories.
    [$articleCategoryData, $associatedCategories] = WikiManager::getArticleCategoryData($id);
    // TODO: this does not fetch all categories, should not check for childID on level=0
    // first get all direct categories and then fetch them all as hierarchies
    // Find if any of the given categories have a main topic.
    [$articleTopic, $articleTopicCategoryID] = self::findTopicCategory($associatedCategories);

    $topicCategories = [];
    $targetID = $articleTopicCategoryID;

    while (true) {
      if (is_null($targetID)) {
        break;
      }
      $found = false;
      foreach ($associatedCategories as $category) {
        if ($category['id'] !== $targetID) {
          continue;
        }
        $found = true;
        $topicCategories[] = $category;
        $targetID = $category['childID'];
      }
      if (!$found) {
        break;
      }
    }

    return [
      'mainTopic' => $articleTopic,
      'topicList' => $topicCategories,
      'categories' => $associatedCategories,
    ];
  }

  /**
   * Returns the article title as HTML.
   */
  private static function getArticleTitle($titleText, $title, $includeLink = false) {
    $buffer = [];
    $items = explode('/', $titleText);
    foreach ($items as $item) {
      $buffer[] = '<span class="segment">'.htmlspecialchars($item).'</span>';
    }
    $text = implode('<span class="separator">/</span>', $buffer);
    if ($includeLink) {
      $link = $title->getLocalUrl();
      return '<a href="'.$link.'">'.$text.'</a>';
    }
    return $text;
  }

  /**
   * Returns the article title text, with namespace.
   */
  private static function getArticleTitleText($articleTitle, $title, $inline) {
    $isTalk = $title->isTalkPage();
    $ns = $title->getNamespace();
    $nsText = $title->getNsText();
    $nsSegment = '<span class="mw-page-title-namespace">'.str_replace('_', ' ', $nsText).'</span>';
    $separatorSegment = '<span class="mw-page-title-separator">:</span>';
    $titleSegment = '<span class="mw-page-title-main">'.self::getArticleTitle($articleTitle, $title, $inline).'</span>';
    if (($ns === 0 && !$isTalk) || empty($nsText)) {
      return $titleSegment;
    }
    else {
      return implode('', [$nsSegment, $separatorSegment, $titleSegment]);
    }
  }

  /**
   * Inserts an article heading with the article title and categories.
   * 
   * Normally, all articles have a #firstHeading that is generated by the Vector skin.
   * This heading is a bit problematic for us, since we float elements to the right
   * that should actually be right next to the first heading as well.
   * 
   * To mitigate this, we actually HIDE the real #firstHeading using CSS, and make a
   * secondary new one that looks the same but is actually located somewhere else.
   */
  public static function generateArticleHeading($title, $parsedCategories = [], $inline = false) {
    // Article base information.
    $id = $title->getID();
    $ns = $title->getNamespace();
    $articleTitle = $title->getText();

    // Generate the title and namespace text.
    $titleText = self::getArticleTitleText($articleTitle, $title, $inline);

    // Since we have the fully parsed output, we can grab the article's pre-parsed categories directly.
    // We'll also grab a list of all categories and their parent categories, if any, and determine
    // which main topic this article is in ("guidebook", "behind the scenes", etc.).
    $categoryData = self::getArticleHeadingCategoryData($id, $parsedCategories);
    $titleMeta = self::getArticleHeadingCategoryLinks($articleTitle, $categoryData);

    $articleHeading = '
      <div class="tc-article-heading '.($inline ? 'tc-inline' : '').' '.(!empty(@$categoryData['mainTopic']) ? 'portal-'.$categoryData['mainTopic'] : '').'" id="tcArticleHeading" data-page-id="'.intval($id).'" data-page-ns="'.htmlspecialchars($ns).'">
        <div class="content">
          <div class="title-wrapper">
            <div class="title">'.$titleText.'</div>
            <div class="meta">'.$titleMeta.'</div>
          </div>
        </div>
        <div class="indicator"></div>
      </div>
    ';

    return $articleHeading;
  }
}
