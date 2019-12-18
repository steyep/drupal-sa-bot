<?php

namespace SecurityAdvisoryBot\Components;

use SecurityAdvisoryBot\Config;

/**
 * Provides functionality for parsing an RSS Feed.
 */
class FeedParser {

  /**
   * Settings and configuration for the app.
   *
   * @var SecurityAdvisoryBot\Config
   */
  protected $config;

  /**
   * Array containing the results of the cURL request (RSS Feed).
   *
   * @var array
   */
  protected $feed;

  /**
   * Information about status of the cURL request.
   *
   * @var array
   */
  protected $curlInfo;

  /**
   * Unix timestamp representing the last time a Security Advisory was reported.
   *
   * @var int
   */
  protected $lastReported;

  /**
   * Constructor.
   *
   * @param SecurityAdvisoryBot\Config $config
   *   Configuration object use for getting/setting the app's configuration.
   */
  public function __construct(Config $config) {
    $this->config = $config;
    $this->lastReported = $this->getLastReported();
    $this->feed = $this->curlGetRequest();
  }

  /**
   * Determine when the application last reported Security Advisories.
   *
   * @return int
   *   Returns UNIX timestamp.
   */
  protected function getLastReported() {
    $last_reported = 0;
    if (($log_file = $this->config->get('last_reported_log'))
      && file_exists($log_file)
      && ($log = @fopen($log_file, 'r'))
      && ($timestamp = fgets($log)) !== FALSE) {
      $last_reported = (int) $timestamp;
    }
    if (@is_resource($log)) {
      fclose($log);
    }
    return $last_reported;
  }

  /**
   * Set the timestamp that the application last reported Security Advisories.
   *
   * @param int $last_report
   *   UNIX timestamp to be set as the value.
   */
  protected function setLastReport($last_report) {
    if ($log = @fopen($this->config->get('last_reported_log'), 'w+')) {
      fwrite($log, $last_report);
      fclose($log);
    }
  }

  /**
   * Use cURL to get the RSS feed to be parsed.
   */
  protected function curlGetRequest() {
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $this->config->get('drupal_security_rss_feed'),
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
    ]);

    $response = curl_exec($curl);
    $this->curlInfo = curl_getinfo($curl);
    curl_close($curl);

    return $response;
  }

  /**
   * Parse the RSS feed and generate Security Advisories.
   *
   * @yield SecurityAdvisoryBot\Components\SecurityAdvisory
   *   Yields a new Security Advisory.
   */
  public function generateSecurityAdvisories() {
    if ($this->curlInfo['http_code'] == 200) {
      $response = simplexml_load_string($this->feed);

      $last_reported = $this->lastReported;
      foreach ($response->channel->item as $item) {
        $item = new SecurityAdvisory($item, $this->config);

        // If this $item was published before the $last_reported timestamp, then
        // we can assume that we've already reported it. If the $item does not
        // contain "7.x" in the description, it's likely that the advisory does
        // not pertain to our Drupal 7 projects.
        if ($item->getPublishedDate()->getTimestamp() < $last_reported
          || strpos($item->getAdvisory()->description, '7.x') === FALSE) {
          continue;
        }

        // If any of our projects include the module, then the Security Advisory
        // is applicable and we should report it.
        if ($item->getAffectedProjects()) {
          // Update the "last reported" tracker.
          $this->lastReported = (new \DateTime())->getTimestamp();
          yield $item;
        }
      }
    }
  }

  /**
   * Save the settings on class teardown.
   */
  public function __destruct() {
    $this->setLastReport($this->lastReported);
  }

}
