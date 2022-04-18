# Docs

This folder is used to generated the docs at https://docs.simple-history.com/.

Site is built using [Nextra](https://github.com/shuding/nextra) and the pages use [MDX](https://mdxjs.com/) for formatting.

## Local development

- `$ nvm use` to switch Node version.
- `$ npm run dev` to start dev server.

## Update list of hooks

[wp-hooks-generator](https://github.com/johnbillion/wp-hooks-generator) is used to generate the list of hooks.

Run the following in the `docs`-folder to update `hooks/actions` and `hooks/filters`:

    $ npm run update-hooks-from-source

## Deploy

Deploy is done using Dokku. Push to dokku repo to deploy to docs.simple-history.com.

    $ git push dokku main:master

## Todo

- [ ] Add link to WordPress.org support forum
- [ ] Add link to Simple-History.com main site
- [ ] Add syntax highlighting to documentation `apply_filters...` and examples.
- [ ] Merge into `main`, no reason to work from branch now when it's up and running.
- [ ] Automattic deploy using GitHub Actions.
