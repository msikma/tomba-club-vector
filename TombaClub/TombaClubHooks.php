<?php

class TombaClubHooks {
  /** Adds a custom portlet to the navigation. */
  private static function addCustomPortlet(&$toolbar, $afterKey, $newKey, $newValue) {
    // We'll add the portlet after a specific other one. Find the index where we'll insert it.
    $index = array_search($afterKey, array_keys($toolbar));
    if ($index === false) {
      // If the portlet isn't found, just add it at the bottom.
      $toolbar[$newKey] = $newValue;
    }
    else {
      // Insert it in between the existing items after the given item.
      $toolbar = (
        array_slice($toolbar, 0, $index + 1, true) + 
        [$newKey => $newValue] + 
        array_slice($toolbar, $index + 1, null, true)
      );
    }
  }

  /** Returns the base path to the extension. */
  private static function getExtensionBaseDir() {
    global $wgExtensionAssetsPath;
    return $wgExtensionAssetsPath.'/TombaClub';
  }

  /** Returns the .mw-parser-output div. */
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

  /** Returns whether we have right panels (thumbnails and infoboxes that float to the right). */
  private static function hasRightPanels($html) {
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

  private static function hasInlineBackdrop($html) {
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

  /**
   * Adds the CC BY-NC-SA 4.0 copyright icon to the footer.
   */
  public static function onSkinAddFooterLinks($skin, $key, &$footerlinks) {
    global $wgRightsUrl;
    $extBaseDir = self::getExtensionBaseDir();

    // Add after "places".
    if ($key !== 'places') {
      return;
    }

    // Quick sanity check to ensure that we're only adding this image if our wiki is set to CC BY-NC-SA 4.0.
    if (!str_contains($wgRightsUrl, 'by-nc-sa/4.0')) {
      return;
    }
    $licenseImage = $extBaseDir.'/assets/cc-by-nc-sa.png';
    $copyrightLink = Title::newFromText('Tomba_Wiki:Copyright')->getLocalURL();
    
    $footerlinks['tc_copyrightlink'] = Html::rawElement('a', ['href' => $copyrightLink], 'Copyright');
    $footerlinks['tc_version'] = Html::rawElement('span', ['class' => 'tc-version'], 'MediaWiki '.MW_VERSION);
    $footerlinks['tc_copyright'] = '
      <div class="tc-copyright">
        <span class="license-text">Licensed under <a href="'.$copyrightLink.'">CC BY-NC-SA 4.0</a>.</span>
        <span class="license-image"><a href="'.$copyrightLink.'"><img src="'.$licenseImage.'" width="62" height="15" /></a></span>
      </div>
    ';
  }

  /**
   * Adds the .right-panel CSS class to the <body> element if applicable.
   * 
   * Pages that have a right floating thumbnail or infobox get a right sidebar.
   */
  public static function onOutputPageParserOutput(&$out, &$parserOutput) {
    // The full HTML output of this page.
    $html = $parserOutput->getText();
    // Check if this page has right floating thumbnails or infoboxes.
    $panels = self::hasRightPanels($html);
    // Check if this page has an inline backdrop.
    $backdrops = self::hasInlineBackdrop($html);

    // If there are panels, enable the right panel class. This activates the right sidebar.
    if ($panels) {
      $out->addBodyClasses('right-panel');
    }
    // If an inline backdrop is present, turn on "alt content" mode for this page.
    if ($backdrops) {
      $out->addBodyClasses('alt-content');
    }
  }

  /**
   * Adds the Tomba Club CSS code to the header.
   */
  public static function onBeforePageDisplay(&$out, &$skin) {
    $extBaseDir = self::getExtensionBaseDir();

    // Sanity check: these custom styles only work with the Vector skin.
    if ($skin->getSkinName() !== 'vector') {
      return;
    }

    // Include the base skin assets.
    $out->addStyle($extBaseDir.'/tc-vector.css');
    $out->addScriptFile($extBaseDir.'/tc-vector.js');

    // Add Roboto font from Google Fonts.
    $out->addLink(['href' => 'https://fonts.googleapis.com', 'rel' => 'preconnect']);
    $out->addLink(['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous']);
    $out->addLink(['href' => 'https://fonts.googleapis.com/css2?family=Roboto+Mono:ital,wght@0,100..700;1,100..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap', 'rel' => 'stylesheet']);
  }

  /**
   * Does various bits of design setup.
   * 
   * Adds the "Powered by Animal Dash" badge, the favicon, the logo, and various other things.
   */
  public static function onSetupAfterCache() {
    global $wgLogo, $wgFavicon, $wgFooterIcons, $wgVectorFeatures;
    $extBaseDir = self::getExtensionBaseDir();

    // Set up the logo and favicon.
    $wgLogo = $extBaseDir.'/assets/logo-tomba.png';
    $wgFavicon = $extBaseDir.'/assets/favicon/favicon-512x512.png';

    // Disable buggy Vector skin features.
    $wgVectorFeatures['collapsiblenav']['global'] = false;
    $wgVectorFeatures['collapsibletabs']['global'] = false;

    // Add "Powered by Animal Dash" badge.
    $wgFooterIcons['poweredby']['mediawiki'] = [
      'src' => $extBaseDir.'/assets/powered-by.png',
      'url' => 'https://www.mediawiki.org/',
      'alt' => "Powered by Animal Dash",
    ];
  }

  /**
   * Adds the Social Media portlet to the sidebar.
   * 
   * This links all of our social media accounts under a separate portlet.
   */
  public static function onSkinBuildSidebar($skin, &$bar) {
    // Add our social media items after the navigation section.
    self::addCustomPortlet($bar, 'navigation', 'Social Media', [
      [
        'text' => 'Discord',
        'href' => 'https://discord.com/invite/zx45UfVP5t',
        'id' => 'socmed_discord',
      ],
      [
        'text' => 'Youtube',
        'href' => 'https://www.youtube.com/@TombaClub',
        'id' => 'socmed_youtube',
      ],
      [
        'text' => 'Twitter',
        'href' => 'https://twitter.com/TombaClub/',
        'id' => 'socmed_twitter',
      ],
    ]);

    // Add a special portlet to the start of the list that will be used by our globe logo.
    // The header will be hidden, and the only item will be replaced with an image.
    $bar = ['Tomba Wiki' => [
      [
        'text' => 'Main page',
        'href' => 'Main_Page',
        'id' => 'tomba_sidebar_logo'
      ]
    ]] + $bar;

    return true;
  }
}
