# readme.branch.md

Before starting to fix things:

❯ vendor/bin/phpcs --report=summary

```
A TOTAL OF 32531 ERRORS AND 0 WARNINGS WERE FOUND IN 67 FILES
--------------------------------------------------------------------------------------------------------------
PHPCBF CAN FIX 28922 OF THESE SNIFF VIOLATIONS AUTOMATICALLY
```

After first run:
`A TOTAL OF 26737 ERRORS WERE FIXED IN 66 FILES`

## Todo och klura på

- Hur testa GitHub-plugin-uppdateraren för `SimplePluginLogger.php` rad 573-574. Hur escape'a, om alls? `wp_kses`?
- Kan `SimpleLegacyLogger.php` tas bort? Verkar inte användas nånstans.
- 