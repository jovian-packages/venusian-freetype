# venusian-freetype

The `freetype` font face driver for Topside's Typography. FreeType reads the font file through
[ext-freetype](https://github.com/php-io-extensions/freetype): metrics, the character map, advances and outlines.

```bash
composer require jovian/venusian-freetype
```

The service provider registers `freetype` on Typography's faces manager. Pick it in Topside's config:

```php
// config/typography.php
'face' => ['default' => env('TOPSIDE_FONT_FACE', 'freetype')],
```

## What it does

`FreeTypeFontFaceDriver::face()` opens one `FreeTypeFontFace` a source (file and face index) with `FT_New_Face`, and
keeps it until `release()`, which closes every face and the FreeType library.

- **Metrics** are FreeType's ascender, descender and height, scaled to the size asked for. The line gap is the height
  less the ascender and descender. FreeType takes the OS/2 typographic metrics when the face sets `USE_TYPO_METRICS`.
- **Glyphs** come from the character map FreeType selected when it opened the face (`FT_Get_Char_Index`).
- **Advances and outlines** are loaded unscaled (`FT_LOAD_NO_SCALE`), so they are in font units, y up. An outline is
  FreeType's `FT_Outline_Decompose` of the glyph: TrueType curves are quadratic, CFF curves cubic, and each contour ends
  with FreeType's line back to its start, then a close. A glyph with no outline gives an empty path.
- A glyph the face does not have: `advance()` answers 0, `outline()` throws. A file FreeType cannot open throws.
- **Variable fonts:** a source's variations are set once, when its face opens, with `FT_Set_Var_Design_Coordinates`.
  Every axis of the font gets a value in the font's axis order: the source's, or the axis's default. An axis the font
  lacks is not passed, and FreeType clamps a value to its axis. Each source is its own `FT_Face`, so two instances of
  one file never share a face. Outlines follow `gvar`, advances `HVAR`, and the ascender, descender and line gap
  `MVAR`. FreeType applies MVAR's `hasc`, `hdsc` and `hlgp` to whichever metrics the face uses, `hhea` included. A
  font that does not vary is drawn as it is. FreeType's error setting the coordinates throws, as does a variable font
  on an ext-freetype build without `FT_Get_MM_Var`.

## Tests

`vendor/bin/pest`, with ext-freetype loaded. The tests write their own small fonts (`tests/Fixtures/TinyFont.php`:
TrueType; CFF through `$cffTable` and `TinyFont::cffTableOf()`; variable fonts through `$axes`, `vary()`, `$hvar` and
`$mvar`), so every expected value is in the test. FreeType and HarfBuzz both read the fixture's variation tables as
written.

For local development against a Topside checkout, keep a `composer.dev.json` (gitignored) that adds a path repository
to it, and install with `COMPOSER=composer.dev.json composer install` on a PHP that loads ext-freetype.

## License

MIT.
