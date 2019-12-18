<?php

namespace SecurityAdvisoryBot;

/**
 * Provides configuration for the SecurityAdvisoryBot.
 */
class Config {

  /**
   * Array to store all configurations.
   *
   * @var array
   */
  private $settings = [];

  /**
   * Constructor.
   *
   * @param array $cli_arguments
   *   Arguments passed in through the command line.
   */
  public function __construct(array $cli_arguments = []) {
    $this->settings = [
      'drupal_security_rss_feed' => 'https://www.drupal.org/security/contrib/rss.xml',
      'slack_webhook' => NULL,
      'slack_channel' => NULL,
      'slack_username' => 'Security Advisory Bot',
      'slack_icon_emoji' => ':druplicon:',
      'jira_endpoint' => NULL,
      // Map the severity of the Security Advisory to a response time (in days).
      'severity_threshold' => [
        // A severity of <=12/25 warrants having the site patched in 30 days.
        12 => 30,
        // A severity of <=18/25 warrants having the site patched in 14 days.
        18 => 14,
        // A severity of <=20/25 warrants having the site patched in 7 days.
        20 => 7,
      ],
    ];
    $this->setCommandLineArgs($cli_arguments);
    $this->settings['projects'] = $this->setProjects();
  }

  /**
   * Set the projects based on the CLI arguments.
   */
  public function setProjects() {
    $project_configuration = [];
    if ($cli_projects = $this->get('projects')) {
      $cli_projects = explode(',', $cli_projects);
    }
    $projects = $cli_projects ?: array_keys($project_configuration);
    return array_intersect_key($project_configuration, array_flip($projects));
  }

  /**
   * Parse the command line arguments and add them to the settings array.
   *
   * @param array $cli_arguments
   *   An array of strings passed from the command line.
   */
  private function setCommandLineArgs(array $cli_arguments) {
    while ($cli_arguments) {
      list($key, $value) = array_pad($cli_arguments, 2, '1');
      // If the $key doesn't start with a hyphen, this isn't a CLI flag so we
      // are going to remove it and move onto the next element to find a key.
      if (strpos($key, '-') !== 0) {
        array_shift($cli_arguments);
        continue;
      }
      // Now that we've confirmed that the $key is referencing a CLI flag, we
      // can remove any prefixed hyphens so that we don't store them in the
      // settings array and replace any hyphen separators with underscores.
      $key = str_replace('-', '_', ltrim($key, '-'));

      // If the $key element contains an equal sign, this is a CLI argument that
      // also defines the key's value.
      if (strpos($key, '=') !== FALSE) {
        array_shift($cli_arguments);
        // Split the string on the equal sign so that we can get the value that
        // was intended to be associated with this key.
        $args = explode('=', $key);
        $this->set($args[0], $args[1]);
        continue;
      }

      // If the first character of the $value is a hyphen, then we know it is
      // another CLI flag which means the $key will act as a boolean flag.
      if (strpos($value, '-') === 0) {
        $this->set($key, TRUE);
        array_shift($cli_arguments);
        continue;
      }
      // We've covered the different scenarios, so we can go ahead and associate
      // the $key setting with $value and remove them from the list.
      $this->set($key, $value);
      array_splice($cli_arguments, 0, 2);
    }
  }

  /**
   * Setter method for configuring the SecurityAdvisoryBot.
   *
   * @param string $config_var
   *   The name of the configuration variable to be set.
   * @param mixed $value
   *   The value to be set.
   */
  public function set($config_var, $value) {
    $this->settings[$config_var] = $value;
    return $this;
  }

  /**
   * Getter method for retrieving a SecurityAdvisoryBot configuration setting.
   *
   * @param string $config_var
   *   The name of the configuration variable to be set.
   * @param mixed $default_value
   *   The value to be returned if the $config_var has not be configured.
   */
  public function get($config_var, $default_value = NULL) {
    if (array_key_exists($config_var, $this->settings)) {
      return $this->settings[$config_var];
    }
    return $default_value;
  }

}
