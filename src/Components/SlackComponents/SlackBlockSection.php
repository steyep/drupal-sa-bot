<?php

namespace SecurityAdvisoryBot\Components\SlackComponents;

/**
 * Creates a Slack Block Section Element.
 *
 * {@link https://api.slack.com/reference/messaging/blocks#section}
 */
class SlackBlockSection extends SlackBlockBase {

  protected $type = 'section';

  /**
   * Add Markdown text to this Section.
   *
   * @param string $text
   *   The string of text to be added.
   * @param array $variables
   *   (Optional) Array of string replacements to be passed to `strtr`.
   */
  public function addText($text, array $variables = []) {
    $this->data['text'] = [
      'type' => 'mrkdwn',
      'text' => strtr($text, $variables),
    ];
    return $this;
  }

  /**
   * Add a field to the array of fields in this Section.
   *
   * @param string $text
   *   The string of text to be added.
   * @param array $variables
   *   (Optional) Array of string replacements to be passed to `strtr`.
   */
  public function addField($text, array $variables = []) {
    $this->data['fields'][] = [
      'type' => 'mrkdwn',
      'text' => strtr($text, $variables),
    ];
    return $this;
  }

  /**
   * Add a button element accessory to this Section.
   *
   * @param string $value
   *   The plain text string value to be displayed on the button.
   * @param string $url
   *   The URL destination of the button.
   */
  public function addButton($value, $url) {
    $this->data['accessory'] = [
      'type' => 'button',
      'text' => ['type' => 'plain_text', 'text' => $value],
      'style' => 'primary',
      'url' => $url,
    ];
    return $this;
  }

}
