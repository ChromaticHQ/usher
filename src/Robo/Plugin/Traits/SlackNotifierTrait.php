<?php

namespace Usher\Robo\Plugin\Traits;

use GuzzleHttp\Client;
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
     * @param \Robo\Result $result
     *   The result of the task to check.
     * @param bool $force
     *   If the notification should be forced.
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
            "Tugboat Base Preview failed to build for %s\nPreview: %s\nDashboard: %s",
            getenv('TUGBOAT_REPO'),
            getenv('TUGBOAT_SERVICE_URL'),
            $dashboard_url
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
        $client = new Client(['timeout' => 5]);
        $client->post($slack_webhook_url, ['body' => json_encode($payload)]);
    }

    /**
     * Determine if we are in a Tugboat base preview.
     *
     * @return bool
     */
    protected function isTugboatBasePreview(): bool
    {
        // Confirm we are in a Tugboat environment.
        if (getenv('TUGBOAT_PREVIEW_ID') === false) {
            $this->say('No Tugboat preview ID was found.');
            return false;
        }
        // Determine if we are building a base preview.
        if (getenv('TUGBOAT_PREVIEW_ID') !== getenv('TUGBOAT_BASE_PREVIEW_ID')) {
            $this->say('No Tugboat base preview found.');
            return false;
        }
        return true;
    }
}
