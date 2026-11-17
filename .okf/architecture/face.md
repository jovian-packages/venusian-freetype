---
type: Module
title: Face
description: FreeTypeFontFaceDriver and FreeTypeFontFace, Typography's freetype face driver.
resource: src/FreeTypeFontFaceDriver.php
tags: [freetype, typography, fonts]
status: draft
generated: { by: claude-fable/5.2, at: 2026-11-17T00:00:00-05:00 }
sources:
  - id: driver
    resource: src/FreeTypeFontFaceDriver.php
    title: FreeTypeFontFaceDriver
  - id: face
    resource: src/FreeTypeFontFace.php
    title: FreeTypeFontFace
  - id: provider
    resource: src/Providers/VenusianFreeTypeServiceProvider.php
    title: VenusianFreeTypeServiceProvider
---

# Driver

Registered as `freetype` on `app('typography.faces')`.[^provider] One `FT_Library`, made on first use. `face($source)`: cached by `FontSource::key()` (path, index, coordinates), else `FT_New_Face(path, index)`; error or null → `TypographyException` "FreeType cannot open <key> as a font: error <n>". `release()`: every face released and `FT_Done_Face`d, then `FT_Done_FreeType`.[^driver]

Variable fonts, in `instance()` at open: skipped when the source has no variations or the face lacks `FT_FACE_FLAG_MULTIPLE_MASTERS` (a font that does not vary is drawn as it is). Else `FT_Get_MM_Var`; one 16.16 value an axis in the font's axis order, the source's value for the axis's tag or `axis->def`; `FT_Done_MM_Var`; `FT_Set_Var_Design_Coordinates`. An axis the font lacks is not passed; FreeType clamps. A failure closes the face and throws. An ext-freetype build without `FT_Get_MM_Var` throws for a variable font, naming the build.[^driver]

# Face

`unitsPerEm()`, `glyphCount()`: the record's `units_per_EM`, `num_glyphs`. `metrics($size)`: `ascender`, `-descender`, `height - ascender + descender`, each × size / em. `glyph($codepoint)`: `FT_Get_Char_Index`. `advance($glyph)`: `FT_Load_Glyph(FT_LOAD_NO_SCALE)`, `glyph->advance->x`; 0 for a glyph outside 0..num_glyphs. `outline($glyph)`: the same load, then `FT_Outline_Decompose` into a `Path`: move (closing the contour before it), line, quad (`conic_to`), cubic; a final close. A glyph outside the range, a load error or a walk error throws. After `release()` every call throws.[^face]

# Measured

FreeType 2.15.0 (Homebrew), 2.14 (Debian 13), 2.13.2 (Ubuntu 24.04):

* `USE_TYPO_METRICS` set → the record's ascender, descender and height are the OS/2 typographic ones.
* `MVAR` `hasc` is added to the ascender whichever metrics the face uses: `hhea` 800 → 900 and typographic 700 → 800 at the axis maximum, with a delta of 100.
* An `fvar` whose instance size is not 4 × axes + 4 (or + 6): no `FT_FACE_FLAG_MULTIPLE_MASTERS`, `FT_Get_MM_Var` answers 6.
* A `gvar` of 0xFF bytes: error 8 from `FT_Set_Var_Design_Coordinates` on 2.15, from `FT_New_Face` on 2.14.
* A CFF contour ends with a line back to its start before the close; so does a TrueType one.
* SF-Pro.ttf at `wght` 650: em 2048, 'A' is glyph 19, advance 1398.

[^driver]: FreeTypeFontFaceDriver
[^face]: FreeTypeFontFace
[^provider]: VenusianFreeTypeServiceProvider
