Since I always forget what standards I use in different projects this file is here to remind me about the standards I use in this project:

- PHP coding standard: PSR2 because that's the standard that I use in other projects.
  `phpcs.xml.dist` is the config used.

- Formatting:
  phpcbf to fix errors and warning.
  Then sometimes Prettier, but befare it's not 100 % stable yet for PHP files.

- phpcs to lint while editing. Lots of code is old but working but was written
  before my editor had nice linting, so much of the code does not lint. This will be fixed.

## How to use php codesniffer

List errors and warnings:

    $ phpcs /path/to/code/myfile.php

Fix things:

    $ phpcbf /path/to/code
