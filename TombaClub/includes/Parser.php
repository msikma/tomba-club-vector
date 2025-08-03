<?php

namespace TombaClub;
use \MediaWiki\MediaWikiServices;

class Parser {
  /**
   * Returns the .mw-parser-output div for a given HTML string.
   */
  private static function getMwParserOutput($html) {
    $doc = new \DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new \DOMXPath($doc);

    // We only need to look for items that are a direct descendant of the .mw-parser-output div.
    $mwParserDiv = $xpath->query('//div[contains(@class, "mw-parser-output")]');
    if ($mwParserDiv->length === 0) {
      return false;
    }
    $mwParserOutput = $mwParserDiv->item(0);
    return $mwParserOutput;
  }

  /**
   * Returns whether we have right panels (thumbnails and infoboxes that float to the right).
   */
  public static function hasRightPanels($html) {
    $mwParserOutput = self::getMwParserOutput($html);
    if ($mwParserOutput === false) {
      return false;
    }

    // Find thumbnails and infoboxes that float right.
    foreach ($mwParserOutput->childNodes as $child) {
      if ($child->nodeType !== XML_ELEMENT_NODE) {
        continue;
      }

      $classAttr = $child->getAttribute('class');
      $classes = preg_split('/\s+/', trim($classAttr));

      // Relevant items will have .tright as well as one of .thumb or .box.
      if (in_array('tright', $classes)) {
        if (in_array('thumb', $classes) || in_array('box', $classes)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Returns whether we have an article heading.
   * 
   * If the page has an article heading, we hide the regular #firstHeading on the page.
   */
  public static function hasArticleHeading($html) {
    $mwParserOutput = self::getMwParserOutput($html);
    if ($mwParserOutput === false) {
      return false;
    }

    // Search for .inline-backdrop divs.
    foreach ($mwParserOutput->childNodes as $child) {
      if ($child->nodeType !== XML_ELEMENT_NODE) {
        continue;
      }
      if ($child->hasAttribute('id') && $child->getAttribute('id') === 'tcArticleHeading') {
        return true;
      }
    }

    return false;
  }

  /**
   * Returns whether we have an inline backdrop item.
   * 
   * Inline backdrop pages don't get the standard white backdrop, as it already contains its own.
   */
  public static function hasInlineBackdrop($html) {
    $mwParserOutput = self::getMwParserOutput($html);
    if ($mwParserOutput === false) {
      return false;
    }

    // Search for .inline-backdrop divs.
    foreach ($mwParserOutput->childNodes as $child) {
      if ($child->nodeType !== XML_ELEMENT_NODE) {
        continue;
      }

      $classAttr = $child->getAttribute('class');
      $classes = preg_split('/\s+/', trim($classAttr));
      if (in_array('inline-backdrop', $classes)) {
        return true;
      }
    }

    return false;
  }
}
