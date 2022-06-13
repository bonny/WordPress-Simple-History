Since I always forget what standards I use in different projects this file is here to remind me about the standards I use in this project:

Code standards is updated to WordPress own (used to be PSR 12).

Uses composer package `dealerdirect/phpcodesniffer-composer-installer` to find PHP_CodeSniffer rules automagically. Just run `composer install` and then `vendor/bin/phpcs`.

Use PHP 7 for now, the WordPress rules crashes on PHP 8 so far (bug fixed but no version with fix released).

- `phpcs.xml.dist` is the config used.

- Formatting:
  phpcbf to fix errors and warning.
  Then sometimes Prettier, but befare it's not 100 % stable yet for PHP files.

- phpcs to lint while editing. Lots of code is old but working but was written
  before my editor had nice linting, so much of the code does not lint. This will be fixed.  
  `$ phpcs` to lint PHP from command line

- **Rector** is used to update code to 7.4 and to refactor code to better quality.
  - Dry run with `❯ vendor/bin/rector process --dry-run`
  - Run without `--dry-run` to write changes.

- Changelog: try to use format from https://keepachangelog.com.

## How to use in Visual Studio Code

- Run `composer install`
- Install plugin https://marketplace.visualstudio.com/items?itemName=ValeryanM.vscode-phpsab

## How to use php codesniffer

List errors and warnings:

    $ phpcs /path/to/code/myfile.php # lint specific file
    $ phpcs # be in plugin root and all files will be linted
    $ npm run lint-php # or use npm script

Fix things:

    $ phpcbf /path/to/code

## Git

- Will try to follow OneFlow:  
  https://www.endoflineblog.com/oneflow-a-git-branching-model-and-workflow
