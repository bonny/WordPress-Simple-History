Site is built using [Nextra](https://github.com/shuding/nextra).
Documents use [MDX](https://mdxjs.com/) for formatting.

## Deploy

Deploy is done using Dokku. Push to dokku repo to deploy to docs.simple-history.com.

    $ git push dokku feature/docs:master

## Update list of hooks

[wp-hooks-generator](https://github.com/johnbillion/wp-hooks-generator) is used to generate the list of hooks.

Run the following in the `docs`-folder to update `hooks/actions` and `hooks/filters`:

`$ ./vendor/bin/wp-hooks-generator --input=.. --output=hooks --ignore-files=../vendor/,vendor/,tests/`

## Todo

- [ ] Add documentation to all actions and filters in plugin.
  - [ ] Test if possible to add markdown and better examples, multiline, directly in comment.
  - [ ] "Fires ..." is what WP uses for actions, "Filters ..." is what WP uses for filters.
- [ ] Get syntax highlighting to work when Nextra is updated to version 2.
