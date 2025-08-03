<?php

namespace TombaClub;
use \MediaWiki\MediaWikiServices;
use \RequestContext;
use \Title;

class TagExtensions {
  /**
   * Renders the <CategoryList /> tag extension.
   */
  public static function renderCategoryListTag($input, $args, $parser, $frame) {
    $category = @$args['name'];
    if (empty($category)) {
      return '<div class="error">'.htmlspecialchars('<CategoryList />').' error: no "name" entered.</div>';
    }
    return trim('
      <div class="category-list">
        <div class="topics special-topics">
          <div class="topic topic-discord">
            <div class="topic-box enclosed">
              <div class="box-header">
                <h3>asdf</h3>
              </div>
              <div class="box-content">
                <div class="content"><a rel="nofollow" class="external text" href="https://discord.gg/GWCjPpy">Come talk</a> with other Tomba fans!</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    ');
  }

  /** 
   * Renders the <LatestTwitterPosts /> tag extension.
   */
  public static function renderLatestTwitterPosts($input, $args, $parser, $frame) {
    $header = @$args['header'];
    $content = $parser->recursiveTagParse($input, $frame);
    $tweets = MainPage::getTwitterPosts();
    return trim('
      <div class="">
        <h3 class="header">'.htmlspecialchars($header).'</h3>
        <div class="topics-container twitter-posts">'.$tweets.'</div>
        <div class="content">'.$content.'</div>
      </div>
    ');
  }

  /** 
   * Renders the <FeaturedArticle /> tag extension.
   */
  public static function renderFeaturedArticle($input, $args, $parser, $frame) {
    $content = MainPage::getFeaturedArticle();
    return trim('
      <div class="featured-article">'.$content.'</div>
    ');
  }

  /** 
   * Renders the <WelcomeSearchForm /> tag extension.
   */
  public static function renderWelcomeSearchForm($input, $args, $parser, $frame) {
    $url = Settings::config()->get('Script');
    $placeholder = @$args['placeholder'];
    return trim('
      <form action="'.$url.'" class="search-bar-home"><input type="hidden" name="title" value="Special:Search">
        <div class="search-bar">
          <div class="search-input"><input type="search" name="search" placeholder="'.htmlspecialchars($placeholder).'" aria-label="Search Tomba! Wiki" autocapitalize="sentences">
            <div class="button-container"><button class="button" type="submit" name="go" title="Submit to search the wiki">Go</button></div>
          </div>
        </div>
      </form>
    ');
  }

  /** 
   * Renders the <LatestYoutubeVideos /> tag extension.
   */
  public static function renderLatestYoutubeVideos($input, $args, $parser, $frame) {
    $header = @$args['header'];
    $content = $parser->recursiveTagParse($input, $frame);
    $videos = MainPage::getYoutubeVideos();
    return trim('
      <div class="">
        <h3 class="header">'.htmlspecialchars($header).'</h3>
        <div class="topics-container youtube-videos">'.$videos.'</div>
        <div class="content">'.$content.'</div>
      </div>
    ');
  }

  /** 
   * Renders the <DiscordInvite /> tag extension.
   */
  public static function renderDiscordInvite($input, $args, $parser, $frame) {
    $header = @$args['header'];
    $content = $parser->recursiveTagParse($input, $frame);
    $stats = MainPage::getDiscordOnlineUsers();
    return trim('
      <div class="topic topic-discord">
        <div class="topic-box">
          <div class="box-header">
            <h3>
              <em>'.htmlspecialchars($header).'</em>
              <em class="stats">'.$stats.'</em>
            </h3>
          </div>
          <div class="box-content">
            <div class="content">'.$content.'</div>
          </div>
        </div>
      </div>
    ');
  }

  /** 
   * Renders the <LatestImageboardPosts /> tag extension.
   */
  public static function renderLatestImageboardPosts($input, $args, $parser, $frame) {
    $header = @$args['header'];
    $content = $parser->recursiveTagParse($input, $frame);
    $posts = MainPage::getImageboardPosts();
    return trim('
      <div class="topic topic-imageboard">
        <div class="topic-box enclosed">
          <div class="box-header">
            <h3>'.htmlspecialchars($header).'</h3>
          </div>
          <div class="box-content imageboard-posts">
            <div class="posts">'.$posts.'</div>
            <div class="content">'.$content.'</div>
          </div>
        </div>
      </div>
    ');
  }
}
