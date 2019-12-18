<?php

namespace SecurityAdvisoryBot\Components;

use SecurityAdvisoryBot\Config;
use SecurityAdvisoryBot\Components\SlackComponents\SlackBlock;

/**
 * Provides methods for interfacing with the Slack API.
 */
class Slack {

  /**
   * Settings and configuration for the app.
   *
   * @var SecurityAdvisoryBot\Config
   */
  private $config;

  /**
   * Constructor.
   *
   * @param SecurityAdvisoryBot\Config $config
   *   Configuration object use for getting/setting the app's configuration.
   */
  public function __construct(Config $config) {
    $this->config = $config;
  }

  /**
   * POST request to be sent to the Slack webhook to send message.
   */
  public function postToSlack($message) {
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $this->config->get('slack_webhook'),
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => [
        'payload' => $this->prepareMessagePayload($message),
      ],
    ]);

    curl_exec($curl);
    curl_close($curl);
  }

  /**
   * Prepare the message payload to be sent to Slack.
   *
   * @param SecurityAdvisoryBot\Components\SecurityAdvisory $advisory
   *   The Security Advisory object provided by the FeedParser.
   */
  protected function prepareMessagePayload(SecurityAdvisory $advisory) {
    $payload = array_filter([
      'channel' => $this->config->get('slack_channel', '@ste'),
      'username' => $this->config->get('slack_username', 'Security Advisory Bot'),
      'icon_emoji' => $this->config->get('slack_icon_emoji'),
      'blocks' => $this->getBlock($advisory)->build(),
    ]);
    return json_encode($payload);
  }

  /**
   * Get the Slack Block to be attached to this message payload.
   *
   * @param SecurityAdvisory $advisory
   *   The Security Advisory object provided by the FeedParser.
   *
   * @return SecurityAdvisoryBot\Components\SlackComponents\SlackBlock
   *   Returns the SlackBlock component.
   */
  private function getBlock(SecurityAdvisory $advisory) {
    $slack_block = new SlackBlock();
    $slack_block->addSection()
      ->addText(':priority-@severity: *<:link|:title>*', [
        '@severity' => $advisory->getSeverityLevel(),
        ':link' => (string) $advisory->getAdvisory()->link,
        ':title' => (string) $advisory->getAdvisory()->title,
      ]);
    $slack_block->addSection()
      ->addField('*Module:* `:module`', [':module' => $advisory->getModule()])
      ->addField('*Published date:* ' . $advisory->getPublishedDate()->format('m/d/Y'))
      ->addField('*Risk:* :risk / 25', [':risk' => $advisory->getRisk()])
      ->addField('*Suggested due date:* ' . $advisory->getSuggestedDueDate()->format('m/d/Y'));
    $slack_block->addDivider()
      ->addSection()
      ->addText('*Affected projects:*');

    foreach ($advisory->getAffectedProjects() as $project) {
      $slack_block->addSection()
        ->addText($project['name'])
        ->addButton('Create a Jira Ticket', $advisory->createLinkToJiraTicket($project));
    }

    return $slack_block;
  }

}
