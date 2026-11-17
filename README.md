# jovian/venusian-freetype

FreeType for Venusian Topside's Typography: the `freetype` font face driver. FreeType reads the font file and answers its metrics, character map, advances and outlines, for TrueType, CFF and variable fonts.

## Requirements

- Linux or macOS, PHP 8.4+.
- [ext-freetype](https://github.com/php-io-extensions/freetype) 0.11, built against FreeType 2.13.2 or later. Variable fonts need a build with `FT_Get_MM_Var`.

## Install

```bash
composer require jovian/venusian-freetype
```

The provider registers `freetype` on Typography's faces manager. Name it in `config/typography.php`, or in the environment:

```php
'face' => ['default' => env('TOPSIDE_FONT_FACE', 'freetype')],
```

## Usage

Nothing changes in application code. Text is set and drawn through Typography, and the faces behind it are FreeType's:

```php
use Surface\NutsAndBolts\Color;
use Surface\Typography\Typography;

$text = Typography::set("Hello\nworld", Typography::font('body'), width: 320);

$surface->render(fn ($g) => $g->text($text, 20, 40, Color::hex('#fff')));
```

A face on its own:

```php
use Surface\Contracts\Typography\FontSource;

$face = app('typography.faces')->driver('freetype')->face(new FontSource('/Library/Fonts/SF-Pro.ttf', 0, ['wght' => 650]));

$face->unitsPerEm();                 // 2048
$glyph = $face->glyph(0x41);         // 'A'
$face->advance($glyph);              // in font units, at wght 650
$face->outline($glyph);              // a Path in font units, y up
$face->metrics(14)->ascent;          // scaled to size 14
```

## Behaviour

- **One face a source.** A source is a file, a face index and, for a variable font, its variation coordinates. Each is opened once with `FT_New_Face` and kept until the driver's `release()`, which closes every face and the FreeType library. Two instances of one file never share a face.
- **Metrics.** FreeType's ascender, descender and height, scaled to the size asked for. The line gap is the height less the ascender and descender. FreeType takes the OS/2 typographic metrics when the font sets `USE_TYPO_METRICS`.
- **Glyphs.** From the character map FreeType selected when it opened the face (`FT_Get_Char_Index`).
- **Advances and outlines.** Loaded unscaled (`FT_LOAD_NO_SCALE`), so they are in font units, y up. An outline is FreeType's `FT_Outline_Decompose` of the glyph: TrueType curves are quadratic, CFF curves cubic, and each contour ends with FreeType's line back to its start, then a close. A glyph with no outline gives an empty path.
- **Variable fonts.** The source's coordinates are set when its face opens (`FT_Set_Var_Design_Coordinates`). Every axis of the font gets a value, in the font's axis order: the source's, or the axis's default. An axis the font lacks is not passed, and FreeType clamps a value to its axis. Outlines follow `gvar`, advances `HVAR`, and the ascender, descender and line gap `MVAR`, which FreeType applies to whichever metrics the face uses, `hhea` included. A font that does not vary is drawn as it is.
- **Refusals.** A `TypographyException` names the source and FreeType's error code for a file FreeType cannot open, a glyph it cannot load, and coordinates it cannot set. `advance()` of a glyph the face does not have answers 0; `outline()` of one throws.
- **No pixels.** The driver gives geometry only. Glyph bitmaps never pass through PHP.

## Testing

See [.okf/runbooks/testing.md](.okf/runbooks/testing.md). The tests write their own fonts, so they need no font installed.

## License

MIT. See [LICENSE](LICENSE).
