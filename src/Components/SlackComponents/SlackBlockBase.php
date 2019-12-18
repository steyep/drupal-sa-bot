<?php

namespace SecurityAdvisoryBot\Components\SlackComponents;

/**
 * Base class creating Slack Blocks.
 *
 * {@link https://api.slack.com/reference/messaging/blocks}
 */
class SlackBlockBase {

  /**
   * Array containing the data to be displayed in the Block layout.
   *
   * @var array
   */
  protected $data = [];

  /**
   * Indicates the type of block element.
   *
   * @var string
   */
  protected $type;

  /**
   * Create a new Block layout instance.
   */
  public function __construct() {
    if ($this->type) {
      $this->data['type'] = $this->type;
    }
  }

  /**
   * Build and format the data for the Slack Block element.
   *
   * @return array
   *   Returns an array of formatted data.
   */
  public function build() {
    return array_map(function ($block) {
      return $block instanceof SlackBlockBase ? $block->build() : $block;
    }, $this->data);
  }

}
