# Chromatic Robo

This repo is intended for use on Chromatic repositories to supplement use of the
[Robo](https://robo.li/) PHP task runner. It contains a number of basic commands
and can be extended in downstream repos.

## Installation

`composer require chromatichq/chq-robo`

## Configuration

1. Create a `robo.yml` file in the root of your codebase. `robo.drupal.example.yml`
is provided as a starting point for Drupal projects.
1. Create a `.sites.config.yml` file in the root of your codebase. See
`.sites.config.example.yml` for reference on what can/should be configured.
1. Add the following to your repo's `composer.json` "scripts" section so that you
can call robo easily with `composer robo`:

```json
"scripts": {
    "robo": "robo --ansi"
}
```

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
