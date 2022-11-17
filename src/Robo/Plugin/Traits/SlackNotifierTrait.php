<?php

namespace Usher\Robo\Plugin\Traits;

use GuzzleHttp\Client;
use NotifierInterface;

/**
 * Trait to provide notification functionality.
 */
trait SlackNotifierTrait
{
    /**
     * Notify Slack.
     *
     * @param string $username
     *   The username to pass to Slack.
     * @param string $text
     *   The text string to pass to the Slack API.
     *
     * @see https://docs.tugboatqa.com/starter-configs/code-snippets/slack-integration/
     */
    public function notifySlack(string $username, string $text): void
    {
        // Verify that a Slack webhook URL was provided.
        $slack_webhook_url = getenv('SLACK_WEBHOOK_URL');
        if ($slack_webhook_url === false || $slack_webhook_url === '') {
            $this->yell('Missing Slack Webhook URL from the "SLACK_WEBHOOK_URL" environment variable.');
            return;
        }
        $payload = [
            'username' => $username,
            'text' => $text,
        ];
        // Send the Slack webhook call.
        $client = new Client(['timeout' => 5]);
        $client->post($slack_webhook_url, json_encode(['body' => $payload]));
    }
}
