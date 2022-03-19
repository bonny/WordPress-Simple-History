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

- [ ] https://nextra.vercel.app/
- [ ] https://nextra.vercel.app/advanced/code-highlighting
- [ ] https://github.com/mdx-js/vscode-mdx
- [ ] Start writing docs
