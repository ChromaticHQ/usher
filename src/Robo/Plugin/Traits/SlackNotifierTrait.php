<?php

namespace Usher\Robo\Plugin\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Robo\Result;

/**
 * Trait to provide notification functionality.
 */
trait SlackNotifierTrait
{
    /**
     * The Tugboat dashboard URL.
     *
     * @var string
     */
    protected $tugboatDashboardUrl = 'https://dashboard.tugboatqa.com';

    /**
     * Notify Slack if a base preview build failed.
     *
     * @see https://docs.tugboatqa.com/starter-configs/code-snippets/slack-integration/
     */
    protected function notifySlackOnFailedBasePreviewBuild(Result $result, bool $force = false): void
    {
        // Confirm we are in a base preview.
        if (!$this->isTugboatBasePreview() && !$force) {
            return;
        }
        // If everything went well there is nothing to do.
        if ($result->wasSuccessful() && !$force) {
            $this->say('Skipping Slack notification since all tasks completed successfully.');
            return;
        }
        // Build various variables and URLs for the Slack message.
        $dashboard_url = sprintf(
            '%s/%s',
            $this->tugboatDashboardUrl,
            getenv('TUGBOAT_PREVIEW_ID'),
        );
        $text = sprintf(
            "Tugboat <%s|Base Preview> failed to build for *%s*.",
            $dashboard_url,
            getenv('TUGBOAT_REPO')
        );
        $this->sendSlackNotification('Tugboat', $text);
    }

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
    protected function sendSlackNotification(string $username, string $text): void
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
        try {
            $client = new Client(['timeout' => 5]);
            $client->post($slack_webhook_url, ['body' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        } catch (RequestException) {
            $this->yell('Slack webhook request failed.');
        }
    }

    /**
     * Determine if we are in a Tugboat base preview.
     */
    protected function isTugboatBasePreview(): bool
    {
        // Confirm we are in a Tugboat environment.
        if (getenv('TUGBOAT_PREVIEW_ID') === false) {
            return false;
        }
        // Determine if we are building a base preview.
        return getenv('TUGBOAT_PREVIEW_ID') === getenv('TUGBOAT_BASE_PREVIEW_ID');
    }
}
