# ILR People Profile Feed Generator

## Requirements

- PHP xsl extension
- [direnv][] for local development (`brew install direnv` on macOS)

## Developer Setup

This is only required for local development. In production, environment variables will be set via the hosting configuration.

- Copy `.envrc.example` to `.envrc` and update values.
- Run `direnv allow`.

## Notes

This should run every night and place the file 'output/ilr_profiles_feed.xml' in a location where it can be retrieved by the Drupal 7 feeds importer.


[direnv]: https://github.com/direnv/direnv#getting-started
