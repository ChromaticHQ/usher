<?php

namespace Usher\Robo\Plugin\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Robo\Result;

/**
 * Trait to provide notification functionality.
 */
trait GitHubNotifierTrait
{
    /**
     * The Tugboat dashboard URL.
     *
     * @var string
     */
    protected $tugboatDashboardUrl = 'https://dashboard.tugboatqa.com';

    /**
     * Notify GitHub PR if TK.
     *
     * @param string $error
     *   TK.
     * @param bool $force
     *   If the notification should be forced.
     *
     * @see https://docs.tugboatqa.com/starter-configs/code-snippets/slack-integration/
     */
    protected function notifyGitHubPR(string $error, bool $force = false): void
    {
        if (!$this->isTugboat() && !$force) {
            return;
        }

        if (!$this->isPullRequest()) {
            return;
        }

        // Build various variables and URLs for the Slack message.
        $dashboard_url = sprintf(
            '%s/%s',
            $this->tugboatDashboardUrl,
            getenv('TUGBOAT_PREVIEW_ID'),
        );

        $text = sprintf(
            "Tugboat <%s|Tugboat environment> *%s*.",
            $dashboard_url,
            $error
        );
        $this->sendSlackNotification('Tugboat', $error);
    }

    /**
     * Create GitHub PR Comment.
     *
     * @param string $error
     *   The text string to pass to the Slack API.
     *
     * @see https://docs.tugboatqa.com/starter-configs/code-snippets/slack-integration/
     */
    protected function sendGitHubPRComment(string $error): void
    {
        $pullRequestNumber = $this->pullRequestNumber();
        if (
            !$githubOrg = getenv('TUGBOAT_GITHUB_OWNER')
            && !$githubRepo = getenv('TUGBOAT_GITHUB_REPO')
            && !$pullRequestNumber
        ) {
            $this->yell('Missing GitHub environment variables.');
            return;
        }

        // Verify that a GitHub token was provided.
        $githubToken = getenv('GITHUB_COMMENT_TOKEN');
        if ($githubToken === false || $githubToken === '') {
            $this->yell('Missing GitHub tokenL from the "GITHUB_COMMENT_TOKEN" environment variable.');
            return;
        }

        $githubApiBaseUrl = 'https://api.github.com';
        $guthubCreateCommentUrl = "$githubApiBaseUrl/repos/$githubOrg/$githubRepo/issues/$pullRequestNumber/comments";
        try {
            $client = new Client(['timeout' => 5]);
            $client->post($guthubCreateCommentUrl, [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => "Bearer $githubToken",
                    'X-GitHub-Api-Version' => '2022-11-28',
                ], 'body' => "⚠️ $error",
            ]);
        } catch (RequestException $exception) {
            $this->yell('GitHub API request failed.');
        }
    }

    /**
     * Determine if we are in a Tugboat environment.
     *
     * @return bool
     *   Boolean indicating if we are interacting with a Tugboat environment.
     */
    protected function isTugboat(): bool
    {
        // Confirm we are in a Tugboat environment.
        if (getenv('TUGBOAT_PREVIEW_ID') === false) {
            return false;
        }
        return true;
    }

    /**
     * TK
     *
     * @return bool
     *   TK
     */
    protected function isPullRequest(): bool
    {
        // Confirm we are in a Tugboat environment.
        if (getenv('TUGBOAT_GITHUB_PR') === false) {
            return false;
        }
        return true;
    }

    /**
     * TK
     *
     * @return int
     *   TK
     */
    protected function pullRequestNumber(): int|bool
    {
        if (!$this->isPullRequest()) {
            return false;
        }
        return (int)getenv('TUGBOAT_GITHUB_PR');
    }
}
