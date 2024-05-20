#### Installation

Add the `bin` subdirectory to your `PATH`, or create a symlink to `bin/psc` in a directory already on your `PATH`.

#### Usage

```sh
psc [-p] [--infile=INFILE|-i INFILE] [--outfile=OUTFILE|-o OUTFILE] conversion1 [conversion 2] [...]
psc [--help|-h]
```

#### Examples

Get help and see available conversions:

```sh
psc --help
```

Perform the conversions `to_alternative` and  `one_tag_per_statement` on the file `my_file.php` in place:

```sh
psc -p -i my_file.php to_alternative one_tag_per_statement
```

Explain on stdout, the code given to stdin:

```sh
psc explain
```

Mark up the file `my_file.php`, saving the result `my_file.php.html`:

```sh
psc -i my_file -o my_file.php.html mark_up
```
