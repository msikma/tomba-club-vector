<?php

namespace TombaClub;
use \MediaWiki\MediaWikiServices;
use \RequestContext;
use \Title;
use CategoryListTag;

class Hooks {
  /**
   * Adds a custom portlet to the navigation section
   */
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

  /**
   * Forces the viewport to include responsive support.
   * 
   * This works around the fact that 1.40+ broke responsive support for Vector 2010.
   */
  public static function onOutputPageAfterGetHeadLinksArray(&$tags, $output) {
    $tags['meta-viewport'] = '<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes, minimum-scale=0.25, maximum-scale=5"/>';
    return true;
  }

  /**
   * Adds the CC BY-NC-SA 4.0 copyright icon to the footer.
   */
  public static function onSkinAddFooterLinks($skin, $key, &$footerlinks) {
    $rightsURL = Settings::getWikiLicenseURL();
    $extBaseDir = Settings::getExtensionBaseDir();

    // Add after "places".
    if ($key !== 'places') {
      return true;
    }

    // Quick sanity check to ensure that we're only adding this image if our wiki is set to CC BY-NC-SA 4.0.
    if (!str_contains($rightsURL, 'by-nc-sa/4.0')) {
      return true;
    }
    $licenseImage = $extBaseDir.'/assets/cc-by-nc-sa.png';
    $copyrightLink = \Title::newFromText('Tomba_Wiki:Copyright')->getLocalURL();
    
    $footerlinks['tc_copyrightlink'] = \Html::rawElement('a', ['href' => $copyrightLink], 'Copyright');
    $footerlinks['tc_version'] = \Html::rawElement('span', ['class' => 'tc-version'], 'MediaWiki '.MW_VERSION);
    $footerlinks['tc_copyright'] = '
      <div class="tc-copyright">
        <span class="license-text">Licensed under <a href="'.$copyrightLink.'">CC BY-NC-SA 4.0</a>.</span>
        <span class="license-image"><a href="'.$copyrightLink.'"><img src="'.$licenseImage.'" width="62" height="15" /></a></span>
      </div>
    ';

    return true;
  }

  /**
   * Adds the .right-panel CSS class to the <body> element if applicable.
   * 
   * Pages that have a right floating thumbnail or infobox get a right sidebar.
   */
  public static function onOutputPageParserOutput(&$out, &$parserOutput) {
    // The full HTML output of this page.
    $html = $parserOutput->getText();

    // If there are panels, enable the right panel class. This activates the right sidebar.
    if (Parser::hasRightPanels($html)) {
      $out->addBodyClasses('right-panel');
    }

    // If an inline backdrop is present, turn on "alt content" mode for this page.
    if (Parser::hasInlineBackdrop($html)) {
      $out->addBodyClasses('alt-content');
    }

    // Add a first heading. The return value indicates if it was added or not.
    if (Parser::hasArticleHeading($html)) {
      $out->addBodyClasses('has-tc-article-heading');
    }

    return true;
  }

  /**
   * Renders the <categorylist /> tag extension.
   */
  public static function renderCategoryListTag($input, $args, $parser, $frame) {
    $category = @$args['name'];
    if (empty($category)) {
      return '<div class="error">'.htmlspecialchars('<categorylist />').' error: no "name" entered.</div>';
    }
    return '<p>hello world('.var_export($args, true).')</p>';
  }

  /**
   * Registers parser hooks.
   */
  public static function onParserFirstCallInit($parser) {
    $parser->setHook('CategoryList', [\TombaClub\TagExtensions::class, 'renderCategoryListTag']);
    $parser->setHook('DiscordInvite', [\TombaClub\TagExtensions::class, 'renderDiscordInvite']);
    $parser->setHook('WelcomeSearchForm', [\TombaClub\TagExtensions::class, 'renderWelcomeSearchForm']);
    $parser->setHook('FeaturedArticle', [\TombaClub\TagExtensions::class, 'renderFeaturedArticle']);
    $parser->setHook('LatestImageboardPosts', [\TombaClub\TagExtensions::class, 'renderLatestImageboardPosts']);
    $parser->setHook('LatestYoutubeVideos', [\TombaClub\TagExtensions::class, 'renderLatestYoutubeVideos']);
    $parser->setHook('LatestTwitterPosts', [\TombaClub\TagExtensions::class, 'renderLatestTwitterPosts']);
    $parser->setHook('TombaEventName', [\TombaClub\TagExtensions::class, 'renderTombaEventName']);
    return true;
  }

  /**
   * Generates an article heading and injects it into the parser output.
   */
  private static function insertArticleHeading($parser, &$text) {
    $context = RequestContext::getMain();
    $title = $parser->getTitle();
    $options = $parser->getOptions();
    $action = $context->getRequest()->getVal('action', 'view');

    // We will only add our custom first heading to the top of regular article content.
    // This hook is actually called when rendering various parts of the page; if this is
    // one of those auxiliary parser calls, $options->getInterfaceMessage() will be non-empty.
    // We also only perform these replacements on "view" and "submit" (since submit recaches the output).
    if (
      !$title ||
      !empty($options->getInterfaceMessage()) ||
      str_contains($text, 'mw-editsection') ||
      $title->getNamespace() === -1 ||
      //$title->getNamespace() !== NS_MAIN ||
      $title->getID() === 1 ||
      $title->isSpecialPage() ||
      //$title->isTalkPage() ||
      !in_array($action, ['view', 'submit'])
    ) {
      return false;
    }

    // If we're here: great, it means we've got a regular article that should get a heading.
    $title = $parser->getTitle();
    $output = $parser->getOutput();
    $categories = array_keys($output->getCategoryMap());
    $firstHeading = ArticleMeta::generateArticleHeading($title, $categories);

    $text = $firstHeading.$text;

    return true;
  }

  /**
   * Replaces magic word placeholders on the main page.
   */
  public static function onParserAfterTidy($parser, &$text) {
    self::insertArticleHeading($parser, $text);
    return true;
  }

  /**
   * Adds the Tomba Club CSS code to the header.
   */
  public static function onBeforePageDisplay(&$out, &$skin) {
    $extBaseDir = Settings::getExtensionBaseDir();

    // Sanity check: these custom styles only work with the Vector skin.
    if ($skin->getSkinName() !== 'vector') {
      return true;
    }

    // Include the base skin assets.
    $out->addStyle($extBaseDir.'/tc-vector.css');
    $out->addScriptFile($extBaseDir.'/tc-vector.js');
    
    // Add our favicon images.
    $out->addLink(['href' => $extBaseDir.'/assets/favicon-512x512.png', 'rel' => 'icon', 'type' => 'image/png', 'sizes' => 'any']);

    // Add Roboto font from Google Fonts.
    $out->addLink(['href' => 'https://fonts.googleapis.com', 'rel' => 'preconnect']);
    $out->addLink(['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous']);
    $out->addLink(['href' => 'https://fonts.googleapis.com/css2?family=Roboto+Mono:ital,wght@0,100..700;1,100..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap', 'rel' => 'stylesheet']);

    return true;
  }

  /**
   * Adds the "purge cache" button to the "more" menu.
   */
  public static function onSkinTemplateNavigation($skinTemplate, &$links) {
    $title = $skinTemplate->getTitle();

    // Skip special and nonexistent pages.
    if ($title->isSpecialPage() || !$title->exists()) {
      return;
    }

    if ($skinTemplate->getUser()->isAllowed('purge')) {
      $skinTemplate->getOutput()->addModules('ext.cargo.purge');
      $links['actions']['cargo-purge'] = [
        'class' => false,
        'text' => 'Purge',
        'href' => $title->getLocalUrl(['action' => 'purge']),
      ];
    }
	}

  /**
   * Does various bits of design setup.
   * 
   * Adds the "Powered by Animal Dash" badge, the favicon, the logo, and various other things.
   */
  public static function onSetupAfterCache() {
    global $wgLogo, $wgFavicon, $wgFooterIcons, $wgVectorFeatures;
    $extBaseDir = Settings::getExtensionBaseDir();

    // Set up the logo.
    $wgLogo = $extBaseDir.'/assets/logo-tomba.png';
    // We'll set up the favicon manually in self::onBeforePageDisplay().
    $wgFavicon = null;

    // Disable buggy Vector skin features.
    $wgVectorFeatures['collapsiblenav']['global'] = false;
    $wgVectorFeatures['collapsibletabs']['global'] = false;

    // Add "Powered by Animal Dash" badge.
    $wgFooterIcons['poweredby']['mediawiki'] = [
      'src' => $extBaseDir.'/assets/powered-by.png',
      'url' => 'https://www.mediawiki.org/',
      'alt' => "Powered by Animal Dash",
    ];

    return true;
  }

  /**
   * Adds the Tomba Wiki globe logo to the sidebar.
   */
  public static function onSkinBuildSidebar($skin, &$bar) {
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
