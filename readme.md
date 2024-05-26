[![MIT license](https://img.shields.io/badge/license-MIT-brightgreen.svg)](https://opensource.org/licenses/MIT)

# Tomba Club Mediawiki Skin

A Mediawiki skin made for the Tomba Club wiki (v2). This skin is purely a set of CSS changes (done in SCSS) to the existing Vector skin.

## Example

Here's what it looks like:

<img align="center" src="resources/preview.png" alt="Tomba Club skin v2 preview" width="1131">

This image might be a bit outdated since this skin will continue to be updated over time.

## Installation

Upload the files somewhere, and then add them as custom stylesheets/scripts in `LocalSettings.php`.

A few other settings are required.

```php
// Disable feature that does not work with this skin.
$wgVectorFeatures['collapsiblenav']['global'] = false;
$wgVectorFeatures['collapsibletabs']['global'] = false;

// Add the favicon.
$wgFavicon = '/resources/favicon-512x512.png';

// Tomba custom footer image.
$wgFooterIcons['poweredby']['mediawiki'] = [
  'src' => '/resources/design/tomba-club-vector-v2/images/powered-by.png',
  'url' => 'https://www.mediawiki.org/',
  'alt' => "Powered by Animal Dash",
];

# Tomba Wiki design enhancements for the Vector skin.
# See <https://github.com/msikma/tomba-club-vector/>

function tombaWikiPageTitle(&$article) {
  $article->getContext()->getOutput()->addHTML('<script>if (encloseTitleNamespace) encloseTitleNamespace();</script>');
}
function tombaWikiCustomizations(&$out, &$skin) {
  if ($skin->getSkinName() !== 'vector') {
    return;
  }
  $out->addMeta('viewport', 'width=360px, initial-scale=1');
  $out->addStyle('/resources/design/tomba-club-vector-v2/tomba-club-vector.css');
  $out->addScriptFile('/resources/design/tomba-club-vector-v2/tomba-club-vector.js');
  $out->addHeadItem('head-js', '<script src="/resources/design/tomba-club-vector-v2/tomba-club-vector-head.js"></script>');
  $out->addLink(array('href' => 'https://fonts.googleapis.com', 'rel' => 'preconnect'));
  $out->addLink(array('href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous'));
  $out->addLink(array('href' => 'https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap', 'rel' => 'stylesheet'));
}
function tombaWikiFooter($skin, $key, &$footerlinks) {
  if ($key !== 'places') {
    return;
  }
  
  $footerlinks['tc_version'] = Html::rawElement('span', ['class' => 'tc-version'], 'MediaWiki '.MW_VERSION);
}

$wgHooks['BeforePageDisplay'][] = 'tombaWikiCustomizations';
$wgHooks['ArticleViewHeader'][] = 'tombaWikiPageTitle';
$wgHooks['SkinAddFooterLinks'][] = 'tombaWikiFooter';
```

Building the CSS requires [SassC 1.43.1](https://sass-lang.com/install) or up.

### Required hack

Vector has a feature where if there's too many tabs in the right navigation, they get collapsed under a "more" menu. This is called "tab collapse". This needs to be hacked out for the Tomba Club skin since the left and right navigation are both left-aligned now, and it mistakenly detects this as there being no space at all.

To do this, edit `mediawiki/skins/Vector/resources/skins.vector.legacy.js/vector.js` and comment out the entire latter half (from `$tabContainer.on('beforeTabCollapse', ..` to the end); see the `hacks` directory in this repository for an example.

## License

MIT license
