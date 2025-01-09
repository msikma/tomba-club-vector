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
   * Adds the Tomba Club CSS code to the header.
   */
  public static function onBeforePageDisplay(&$out, &$skin) {
    $extBaseDir = self::getExtensionBaseDir();

    // Sanity check: these custom styles only work with the Vector skin.
    if ($skin->getSkinName() !== 'vector') {
      return;
    }

    // Include the base skin assets.
    $out->addMeta('viewport', 'width=360px, initial-scale=1');
    $out->addStyle($extBaseDir.'/tc-vector.css');
    $out->addScriptFile($extBaseDir.'/tc-vector.js');

    // Add Roboto font from Google Fonts.
    $out->addLink(['href' => 'https://fonts.googleapis.com', 'rel' => 'preconnect']);
    $out->addLink(['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous']);
    $out->addLink(['href' => 'https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap', 'rel' => 'stylesheet']);
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

    return true;
  }
}
