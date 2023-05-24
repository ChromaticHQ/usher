<?php

namespace Usher\Robo\Plugin\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Robo\Result;

/**
 * Trait to allow setting of status checks on GitHub PRs from Tugboat.
 *
 * The methods in this trait will only work as expected when run in a Tugboat
 * environment.
 */
trait GitHubStatusTrait
{
    /**
     * The error value when setting a GitHub check status.
     *
     * @var string
     */
    protected $checkStatusError = 'error';

    /**
     * The pending value when setting a GitHub check status.
     *
     * @var string
     */
    protected $checkStatusPending = 'pending';

    /**
     * The success value when setting a GitHub check status.
     *
     * @var string
     */
    protected $checkStatusSuccess = 'success';

    /**
     * The GitHub API Base URL.
     *
     * @var string
     */
    protected $githuApiBaseUrl = 'https://api.github.com';

    /**
     * The Tugboat dashboard URL.
     *
     * @var string
     */
    protected $tugboatDashboardUrl = 'https://dashboard.tugboatqa.com';

    /**
     * Set GitHub PR status check to pending.
     *
     * @param string $gitHubCheckName
     *   The name of the status check.
     */
    protected function setGitHubStatusPending(string $gitHubCheckName): void
    {
        $this->yell("Setting pending status on GitHub check: $gitHubCheckName");
        $this->setGitHubStatus($this->checkStatusPending, $gitHubCheckName);
    }

    /**
     * Set GitHub PR status check to success.
     *
     * @param string $gitHubCheckName
     *   The name of the status check.
     * @param string $gitHubCheckDescription
     *   The description text to associate with the status check.
     * @param string $gitHubCheckUrl
     *   The URL the status check will link to.
     */
    protected function setGitHubStatusSuccess(
        string $gitHubCheckName,
        string $gitHubCheckDescription,
        string $gitHubCheckUrl = null,
    ): void {
        $this->yell("Setting success status on GitHub check: $gitHubCheckName");
        $this->say($gitHubCheckDescription);
        $this->setGitHubStatus($this->checkStatusSuccess, $gitHubCheckName, $gitHubCheckDescription, $gitHubCheckUrl);
    }

    /**
     * Set GitHub PR status check to error.
     *
     * @param string $gitHubCheckName
     *   The name of the status check.
     * @param string $gitHubCheckDescription
     *   The description text to associate with the status check.
     * @param string $gitHubCheckUrl
     *   The URL the status check will link to.
     */
    protected function setGitHubStatusError(
        string $gitHubCheckName,
        string $gitHubCheckDescription,
        string $gitHubCheckUrl = null,
    ): void {
        $this->yell("Setting failure status on GitHub check: $gitHubCheckName");
        $this->say($gitHubCheckDescription);
        $this->setGitHubStatus($this->checkStatusError, $gitHubCheckName, $gitHubCheckDescription, $gitHubCheckUrl);
    }

    /**
     * Set GitHub PR status check.
     *
     * @param string $state
     *   The state string.
     * @param string $gitHubCheckName
     *   The name of the status check.
     * @param string $checkDescription
     *   The descriptive text to set on the check.
     * @param string $targetUrl
     *   The URL the status check will link to.
     *
     * @see https://docs.github.com/en/rest/commits/statuses?apiVersion=2022-11-28#create-a-commit-status
     */
    protected function setGitHubStatus(
        string $state,
        string $gitHubCheckName,
        string $checkDescription = '',
        string $targetUrl = null,
    ): void {
        $tugboatPreviewID = getenv('TUGBOAT_PREVIEW_ID');
        $tugboatPreviewSHA = getenv('TUGBOAT_PREVIEW_SHA');
        $gitHubOrg = getenv('TUGBOAT_GITHUB_OWNER');
        $gitHubRepo = getenv('TUGBOAT_GITHUB_REPO');

        $githubStatusUrl = "$this->githuApiBaseUrl/repos/$gitHubOrg/$gitHubRepo/statuses/$tugboatPreviewSHA";

        $gitHubAccessToken = getenv('GITHUB_ACCESS_TOKEN');
        $body = [
            'state' => $state,
            'context' => $gitHubCheckName,
            'target_url' => $targetUrl ?? "$this->tugboatDashboardUrl/$tugboatPreviewID",
        ];
        if (strlen($checkDescription) > 0) {
            $body['description'] = $checkDescription;
        }
        try {
            $client = new Client(['timeout' => 5]);
            $client->post($githubStatusUrl, [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => "Bearer $gitHubAccessToken",
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
                'body' => json_encode($body, JSON_THROW_ON_ERROR),
            ]);
        } catch (RequestException $exception) {
            $this->yell('GitHub status request failed.');
            $this->say($exception->getMessage());
        }
    }
}
