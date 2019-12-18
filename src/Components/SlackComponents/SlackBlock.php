<?php

namespace SecurityAdvisoryBot\Components\SlackComponents;

/**
 * Provides methods for creating a Slack Block.
 */
class SlackBlock extends SlackBlockBase {

  /**
   * Add a Section to this Block.
   *
   * @return SecurityAdvisoryBot\Components\SlackComponents\SlackBlockSection
   *   Returns a newly instantiated SlackBlockSection
   */
  public function addSection() {
    $section = new SlackBlockSection();
    $this->data[] = $section;
    return end($this->data);
  }

  /**
   * Add a Divider to this Block.
   */
  public function addDivider() {
    $this->data[] = new SlackBlockDivider();
    return $this;
  }

}
