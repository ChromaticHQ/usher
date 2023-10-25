# Usher

Usher is intended for use on Chromatic repositories to supplement the use of the
[Robo](https://robo.li/) PHP task runner. It contains a number of commands to
assist in development, builds, and deployments, and can be extended in
downstream repos.

* ush·er _(verb)_: to show or guide (someone) somewhere.
* ush·er _(noun)_: American singer, songwriter, businessman, and dancer.

![Usher in a tuxedo](https://user-images.githubusercontent.com/20355/146567165-6a9a6dc5-66cd-4f7c-8e39-69de09365bfd.jpg)

## Compatibility

| Usher Version | PHP | Drupal | Notes |
| --- | --- | --- | --- |
| 4.x | 8.1+ | 9.5 - 10.1+ | **Active development**
| 3.x | 8.1+ | 9.5 - 10.1+ | **Minimally maintained**
| 2.x | 7.4 - 8.1 | 7, 9.4 - 10.0 | **Minimally maintained**
| 1.x | 7.4 | 7, 8 | **Unsupported**

## Installation

`composer require chromatic/usher`

## Configuration

- Create a `robo.yml` file in the root of your codebase. `robo.drupal.example.yml`
is provided as a starting point for Drupal projects.
- Create a `.sites.config.yml` file in the root of your codebase. See
`.sites.config.example.yml` for reference on what can/should be configured.
- Add the following to your repo's `composer.json` "scripts" section so that you can call robo easily with `composer robo`:
  ```json
  "scripts": {
    "robo": "robo --ansi"
  }
  ```
- If you are overriding any commands, add the following to your repo's `composer.json` file. Be sure to specify your own namespace prefix in place of `YOURPREFIX`.
  ```json
  "autoload": {
  "psr-4": {
      "YOURPREFIXRobo\\": "robo/src"
    }
  }
  ```
  Then place the commands in `robo/src/Robo/Plugin/Commands` with the
  `YOURPREFIXRobo\Robo\Plugin\Commands` namespace.
- If you are using any of the commands that use the [`GitHubStatusTrait`](src/Robo/Plugin/Traits/GitHubStatusTrait.php) to set status checks on your pull requests, a personal access token must be created in GitHub.
   1. Create a [personal access token](https://github.com/settings/tokens/new) in GitHub.
   1. Create an environment variable named `GITHUB_ACCESS_TOKEN` with the token value in the environment where the checks will run.

## Commands

### `robo dev:refresh`

The `dev:refresh` command, often available in downstream repos as
`composer robo dev:refresh -- SITENAME`, refreshes your local
development environment (DDEV) which includes:

1. `composer install`
1. Theme build (`robo theme:build`).
1. Disabling front-end caches.
1. Downloading the most recent database dump.
1. Importing the database dump.
1. `drush deploy`
1. Generating a Drupal login link.

### `robo deploy:drupal`

The `deploy:drupal` command, often available in downstream repos as
`composer robo deploy:drupal SITENAME DOCROOT`, deploys Drupal in a standardized way
which includes:

1. `drush deploy`
1. `drush config:import`

### `robo drupal:status-report`

The `drupal:status-report` command, often available in downstream repos as
`composer robo drupal:status-report`, checks Drupal's status report and reports
warnings and errors. Specific warnings or errors can be ignored by adding the
warning/error ID to the `drupal_status_report_ignore_checks` key in the
downstream repository's `robo.yml` config file. For example:

```yaml
drupal_status_report_ignore_checks:
  - update_core
```

To identify the ID's in question, run `drush status-report --format=yaml`. The
output will include the ID's.

### `robo config:update-php-version`

Use the `config:update-php-version` command to update the PHP version configured
for a project in files listed in the `php_version_config_paths` key of
`robo.yml`:

```yaml
php_current_version: '8.1'
php_version_config_paths:
  - composer.json
  - .tugboat/config.yml
  - .platform.app.yaml
```

#### Slack Notification Options

If you would like to have Usher send a notification to Slack, you need to [create a Slack app in your workspace](https://api.slack.com/apps), and then add an "incoming webhook" for the channel where you would like the notification sent. When you create the webhook, Slack will provide you with a unique URL for that channel. You must then set the `SLACK_WEBHOOK_URL` environment variable to the Slack webhook URL in whatever environment Usher is running in.

Sample webhook URL: `https://hooks.slack.com/services/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`

- `notify-slack` - Default to `false`. If `true`, Slack notification will be sent on build failure in Tugboat.
- `notify-slack-force` - Default to `false`. If `true`, it will force an attempt to notify Slack about the build regardless of what happened.

## Extending

You can use this package for the basics and then build upon it. New commands
that are relevant only to a single repo should be added to a top-level `/robo`
directory in the project repo. Commands should live at `/robo/src/Robo/Plugin/Commands/`.
Add a new autoload namespace to `composer.json` so these commands will be
detected:

```json
"autoload": {
    "psr-4": {
        "YOURPREFIXRobo\\": "robo/src"
    }
}
```

## Contributing

If you have a new command that would be useful to multiple repositories that use
this package, create a new command here under `/src/Robo/Plugin/Commands` via a
pull request.
