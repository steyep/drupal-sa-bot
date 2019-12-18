<?php

namespace SecurityAdvisoryBot\Components;

use SecurityAdvisoryBot\Config;

/**
 * Provides functionality for parsing an RSS Feed.
 */
class SecurityAdvisory {

  /**
   * Settings and configuration for the app.
   *
   * @var SecurityAdvisoryBot\Config
   */
  protected $config;

  /**
   * XML element parsed from an RSS feed.
   *
   * @var \SimpleXMLElement
   */
  protected $advisory;

  /**
   * The date that this Security Advisory was published.
   *
   * @var \DateTime
   */
  private $publishedDate;

  /**
   * An array of values parsed from the XML element's description property.
   *
   * @var array
   */
  private $parsedDescription;

  /**
   * An array of projects affected by this Security Advisory.
   *
   * @var array
   */
  private $affectedProjects;

  /**
   * A statically cached `awk` variable.
   *
   * @var string
   */
  private static $projectNameIndex;

  /**
   * Severity threshold of the Security Advisory.
   *
   * @var int
   */
  private $threshold;

  /**
   * Constructor.
   *
   * @param \SimpleXMLElement $advisory
   *   XML element parsed from an RSS feed.
   * @param SecurityAdvisoryBot\Config $config
   *   Configuration object use for getting/setting the app's configuration.
   */
  public function __construct(\SimpleXMLElement $advisory, Config $config) {
    $this->config = $config;
    $this->advisory = $advisory;
  }

  /**
   * Get the value of a field that was parsed from the description.
   *
   * @param string $field
   *   The field for which to retrieve the value.
   *
   * @return string
   *   Returns the value of the field if it was successfully parsed.
   */
  private function getFieldFromDescription($field) {
    if ($this->parsedDescription === NULL) {
      $this->parsedDescription = $this->parseDescription();
    }
    return $this->parsedDescription[$field] ?? NULL;
  }

  /**
   * Parse the description for fields matching defined patterns.
   *
   * @return array
   *   Returns an array of the parsed values keyed by field name.
   */
  private function parseDescription() {
    // Regular Expression patterns used to match strings in the description.
    $patterns = [
      'module' => 'project/(?<module>\w+)',
      'risk' => 'Security risk[^\d]+(?<risk>\d+)',
      'version' => 'releases/(?<version>7[\.x\d-]+)',
    ];
    // Parse the name of the module & the criticality from the description.
    $regular_expression = '%' . implode('|', $patterns) . '%';
    preg_match_all($regular_expression, $this->advisory->description, $matches);
    foreach ($patterns as $key => &$value) {
      $value = max($matches[$key] ?? []);
    }
    return $patterns;
  }

  /**
   * Get the XML element from which this Security Advisory was built.
   *
   * @return \SimpleXMLElement
   *   XML element parsed from an RSS feed.
   */
  public function getAdvisory() {
    return $this->advisory;
  }

  /**
   * Get the date this Advisory was published.
   *
   * @return \DateTime
   *   Returns the DateTime object of the published date.
   */
  public function getPublishedDate() {
    if ($this->publishedDate === NULL) {
      $published_date = strtotime($this->advisory->pubDate);
      $this->publishedDate = new \DateTime("@$published_date");
    }
    return $this->publishedDate;
  }

  /**
   * Getter method for getting the Security Advisory's module.
   *
   * @return string
   *   The value that was parsed from the description.
   */
  public function getModule() {
    return $this->getFieldFromDescription('module');
  }

  /**
   * Getter method for getting the Security Advisory's risk.
   *
   * @return string
   *   The value that was parsed from the description.
   */
  public function getRisk() {
    return $this->getFieldFromDescription('risk');
  }

  /**
   * Getter method for getting the Security Advisory's version.
   *
   * @return string
   *   The value that was parsed from the description.
   */
  public function getVersion() {
    return $this->getFieldFromDescription('version');
  }

  /**
   * Get the severity level of the notice.
   *
   * @return string
   *   A string indicating a "low", "medium", or "high" severity.
   */
  public function getSeverityLevel() {
    $severity_levels = ['low', 'medium', 'high'];
    if (($threshold = $this->getThreshold()) < 25) {
      $threshold_map = array_keys($this->config->get('severity_threshold'));
      $threshold_index = array_search($threshold, $threshold_map);
      $severity_index = floor(($threshold_index / 3) * 3);
      return $severity_levels[$severity_index];
    }
    return 'high';
  }

  /**
   * Determine which projects are using a specified module.
   *
   * @return array
   *   Returns an array of project names that include the specified module.
   */
  public function getAffectedProjects() {
    if ($this->affectedProjects === NULL) {
      $project_directory = $this->config->get('project_directory');
      if (!($project_name_index =& static::$projectNameIndex)) {
        // Determine the position of the project name for the `awk` command.
        $path_pieces = explode(DIRECTORY_SEPARATOR, $project_directory);
        // Prefix the position with a dollar sign for the `awk` command.
        static::$projectNameIndex = '$' . (count($path_pieces) + 1);
      }

      $awk_command = "awk -F'/' '{ print {$project_name_index} }'";
      $find_command = 'find :project_dir \( -path "*/sites/all/*" -o -path "*/profiles/*" \) -mindepth 1 -type d -regex "^.*/contrib/:module" | ' . $awk_command;
      $find_command = strtr($find_command, [
        ':project_dir' => $project_directory,
        ':module' => $this->getModule(),
      ]);

      exec($find_command, $projects, $exit_status);
      if ($this->affectedProjects = $exit_status === 0 ? $projects : FALSE) {
        $this->affectedProjects = array_intersect_key($this->config->get('projects'), array_flip($projects));
      }
    }

    return $this->affectedProjects;
  }

  /**
   * Determine a suggested due date for the notice.
   *
   * @return \DateTime
   *   Returns the date object to use as the due date.
   */
  public function getSuggestedDueDate() {
    $published_date = clone $this->getPublishedDate();
    if ($window = $this->config->get('severity_threshold')[$this->getThreshold()] ?? 0) {
      return $published_date->add(new \DateInterval('P' . $window . 'D'));
    }
    // If the `risk` is higher than all of the defined severity thresholds, this
    // is a critical security advisory that should be a hotfix released today.
    return $published_date;
  }

  /**
   * Get the threshold for this Security Advisory.
   *
   * @return int
   *   Severity threshold that corresponds to the number of days before a patch
   *   should be applied.
   */
  public function getThreshold() {
    if ($this->threshold === NULL) {
      foreach ($this->config->get('severity_threshold') as $this->threshold => $window) {
        if ($this->getFieldFromDescription('risk') <= $this->threshold) {
          return $this->threshold;
        }
      }
      $this->threshold = 25;
    }
    return $this->threshold;
  }

  /**
   * Create Jira link.
   */
  public function createLinkToJiraTicket($project) {
    return $this->config->get('jira_endpoint') . '?' . http_build_query([
      'pid' => $project['pid'],
      // Set the ID of the `issuetype` to "Hotfix".
      'issuetype' => 10001,
      'description' => strtr('Update {{:module}} to :version :brSecurity Advisory: :link', [
        ':module' => $this->getModule(),
        ':version' => $this->getVersion(),
        ':br' => PHP_EOL . PHP_EOL,
        ':link' => $this->getAdvisory()->link,
      ]),
      'summary' => 'Security update: ' . $this->getModule(),
      'duedate' => $this->getSuggestedDueDate()->format('d/M/y'),
      'priority' => ['high' => 3, 'medium' => 4, 'low' => 5][$this->getSeverityLevel()],
    ]);
  }

}
