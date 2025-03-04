<?php

namespace IlrProfilesDataFeed;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class SlackLogger extends AbstractLogger {

  protected array $icons = [
    LogLevel::EMERGENCY => ':rotating_light:',
    LogLevel::ALERT => ':rotating_light:',
    LogLevel::CRITICAL => ':rotating_light:',
    LogLevel::ERROR => ':warning:',
    LogLevel::WARNING => ':warning:',
    LogLevel::NOTICE => ':memo:',
    LogLevel::INFO => ':information_source:',
    LogLevel::DEBUG => ':ladybug:',
  ];

  public function __construct(
    protected readonly string $slack_webhook_url,
    protected readonly string $channel,
    protected readonly ?string $host = null,
  ) {}

  public function log($level, string|\Stringable $message, mixed $context = []): void {
    $data = [
      "blocks" => [
        [
          "type" => "context",
          "elements" => [
            [
              "type" => "plain_text",
              "text" => $this->icons[$level] ?? (string) $level,
              "emoji" => true,
            ],
            [
              "type" => "plain_text",
              "text" => $this->channel,
              "emoji" => true,
            ],
            [
              "type" => "plain_text",
              "text" => empty($this->host) ? gethostname() . '/' . gethostbyname(gethostname()) : $this->host,
              "emoji" => true,
            ]
          ]
        ],
        [
          "type" => "section",
          "text" => [
            "type" => "mrkdwn",
            "text" => "```$message```",
          ]
        ],
      ]
    ];

    if ($context) {
      $footer = [
      ];

      foreach ($context as $context_item) {
        $footer[] = [
          "type" => "plain_text",
          "text" => $context_item,
          "emoji" => true,
        ];
      }

      $data['blocks'][] = [
        "type" => "context",
        "elements" => $footer,
      ];
    }

    $stream_context  = stream_context_create([
      'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/json',
        'content' => json_encode($data),
      ]
    ]);

    file_get_contents($this->slack_webhook_url, false, $stream_context);
  }

}
