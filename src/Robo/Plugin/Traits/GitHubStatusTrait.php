<?php

namespace Usher\Robo\Plugin\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Robo\Result;

/**
 * Trait to allow setting of status checks on GitHub PRs from Tugboat.
 */
trait GitHubStatusTrait
{
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
        $this->say('Setting pending status.');
        $this->setGitHubStatus('pending', $gitHubCheckName);
    }

    /**
     * Set GitHub PR status check to success.
     *
     * @param string $gitHubCheckName
     *   The name of the status check.
     */
    protected function setGitHubStatusSuccess(string $gitHubCheckName): void
    {
        $this->say('Setting success status.');
        $checkDescription = 'Drupal status report shows no unexpected warnings or errors.';
        $this->setGitHubStatus('success', $gitHubCheckName, $checkDescription);
    }

    /**
     * Set GitHub PR status check to error.
     *
     * @param string $gitHubCheckName
     *   The name of the status check.
     */
    protected function setGitHubStatusError(string $gitHubCheckName): void
    {
        $this->say('Setting failure status.');
        $checkDescription = 'Drupal status report shows one or more unexpected warnings or errors.';
        $this->setGitHubStatus('error', $gitHubCheckName, $checkDescription);
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
     *
     * @see https://docs.github.com/en/rest/commits/statuses?apiVersion=2022-11-28#create-a-commit-status
     */
    private function setGitHubStatus(
        string $state,
        string $gitHubCheckName,
        string $checkDescription = ''
    ): void {
        $tugboatPreviewID = getenv('TUGBOAT_PREVIEW_ID');
        $tugboatPreviewSHA = getenv('TUGBOAT_PREVIEW_SHA');
        $gitHubOrg = getenv('TUGBOAT_GITHUB_OWNER');
        $gitHubRepo = getenv('TUGBOAT_GITHUB_REPO');

        $githubStatusUrl = "$this->githuApiBaseUrl/repos/$gitHubOrg/$gitHubRepo/statuses/$tugboatPreviewSHA";

        $gitHubAccessToken = getenv('GITHUB_ACCESS_TOKEN');
        $payload = [
            'state' => $state,
            'context', "ci\\$gitHubCheckName",
        ];
        if (strlen($checkDescription) > 0) {
            $payload['description'] = $checkDescription;
            $payload['target_url'] = "$this->tugboatDashboardUrl/$tugboatPreviewID";
        }
        try {
            $client = new Client(['timeout' => 5]);
            $client->post($githubStatusUrl, [
                'headers' => [
                    'Authorization:' => "token $gitHubAccessToken"
                ],
                'body' => json_encode($payload),
            ]);
        } catch (RequestException $exception) {
            $this->yell('GitHub status request failed.');
        }
    }
}
