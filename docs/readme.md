Site is built using [Nextra](https://github.com/shuding/nextra).
Documents use [MDX](https://mdxjs.com/) for formatting.

## Deploy

Deploy is done using Dokku. Push to dokku repo to deploy to docs.simple-history.com.

    $ git push dokku feature/docs:master

## Update list of hooks

[wp-hooks-generator](https://github.com/johnbillion/wp-hooks-generator) is used to generate the list of hooks.

Run the following in the `docs`-folder to update `hooks/actions` and `hooks/filters`:

    $ npm run update-hooks-from-source

## Todo

- [ ] Add documentation to all actions and filters in plugin.
  - [ ] Select one filter to add for with mulitple examples, check forums for good example. Capability? do_log?
  - [ ] Test if possible to add markdown and better examples, multiline, directly in comment.
  - [ ] "Fires ..." is what WP uses for actions, "Filters ..." is what WP uses for filters.
- [ ] Syntax highlighting for `apply_filters...`
