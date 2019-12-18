<?php

namespace SecurityAdvisoryBot;

use SecurityAdvisoryBot\Components\FeedParser;
use SecurityAdvisoryBot\Components\Slack;

/**
 * SecurityAdvisoryBot class.
 */
class SecurityAdvisoryBot {

  /**
   * Drupal Security Advisory RSS Feed Parser.
   *
   * @var SecurityAdvisoryBot\Components\FeedParser
   */
  private $feed;

  /**
   * Class for interfacing with the Slack API to post alerts.
   *
   * @var SecurityAdvisoryBot\Components\Slack
   */
  private $slack;

  /**
   * Constructor.
   */
  public function __construct(FeedParser $feed, Slack $slack) {
    $this->feed = $feed;
    $this->slack = $slack;
  }

  /**
   * Entry point to the application invoked by the container builder.
   */
  public function __invoke() {
    foreach ($this->feed->generateSecurityAdvisories() as $advisory) {
      /** @var SecurityAdvisoryBot\Components\SecurityAdvisory $advisory */
      $this->slack->postToSlack($advisory);
    }
    exit;
  }

}
