<?php

/**
 * @file
 * Entry point to Security Advisory Bot.
 */

require_once __DIR__ . '/vendor/autoload.php';

use DI\ContainerBuilder;
use function DI\create;
use function DI\get;
use SecurityAdvisoryBot\Components\FeedParser;
use SecurityAdvisoryBot\Components\Slack;
use SecurityAdvisoryBot\SecurityAdvisoryBot;
use SecurityAdvisoryBot\Config;
use SecurityAdvisoryBot\Components\SecurityAdvisory;
use SecurityAdvisoryBot\Components\SlackComponents\SlackBlock;

$container = (new ContainerBuilder())
  ->useAutowiring(FALSE)
  ->useAnnotations(FALSE)
  ->addDefinitions([
    Config::class => create(Config::class)->constructor(get('argv')),
    SecurityAdvisoryBot::class => create(SecurityAdvisoryBot::class)->constructor(get(FeedParser::class), get(Slack::class)),
    FeedParser::class => create(FeedParser::class)->constructor(get(Config::class)),
    Slack::class => create(Slack::class)->constructor(get(Config::class)),
    SecurityAdvisory::class => create(SecurityAdvisory::class)->constructor(get(Config::class)),
    SlackBlock::class => create(SlackBlock::class),
    'argv' => $argv,
  ])
  ->build();

$security_advisory_bot = $container->get(SecurityAdvisoryBot::class);
$security_advisory_bot();
