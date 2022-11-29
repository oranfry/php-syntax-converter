#### Installation

Add the `bin` subdirectory to your `PATH`, or create a symlink to `bin/psc` in a directory already on your `PATH`.

#### Usage

```
psc [-p] [--infile=INFILE|-i INFILE] [--outfile=OUTFILE|-o OUTFILE] conversion1 [conversion 2] [...]
psc [--help|-h]
```

Available conversions:
- `explain`
- `mark_up`
- `to_alternative`
- `pass_thru`
- `one_tag_per_statement`

#### Examples

Get help and see available conversions:

```
psc --help
```

Perform the conversions `to_alternative` and  `one_tag_per_statement` on the file `my_file.php` in place:

```
psc -p -i my_file.php to_alternative one_tag_per_statement
```

Explain on stdout, the code given to stdin:

```
psc explain
```

Mark up the file `my_file.php`, saving the result `my_file.php.html`:

```
psc -i my_file -o my_file.php.html mark_up
```

Do conversions on an operating system pipeline (the old way):

```
cat my_file.php \
    | ~/tools/php-syntax-converter/bin/to-alternative.php \
    | ~/tools/php-syntax-converter/bin/one-tag-per-statement.php \
    > /tmp/code.php \
    && mv /tmp/code.php my_file.php
```
