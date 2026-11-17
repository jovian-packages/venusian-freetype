# Log

## 2026-11-17

* Created the bundle. README and SECURITY.md in the house style; `.github/workflows/tests.yml`. The suite runs in a workbench, never the repo root: `composer.dev.json` and the repo's own `vendor/` are gone. [testing](runbooks/testing.md)

## 2026-11-16

* **0.11.0**: the `freetype` font face driver for Topside's Typography, on ext-freetype. [face](architecture/face.md)
* **Variable fonts**: a source's coordinates are set when its face opens; one `FT_Face` a source key. Needs ext-freetype with `FT_Get_MM_Var`, `FT_Done_MM_Var` and `FT_Set_Var_Design_Coordinates`. [face](architecture/face.md)
* **TinyFont** (the tests' font writer) writes `fvar` with instance size 4 × axes + 4 and `gvar` x and y deltas as two packed arrays: FreeType reads any other instance size as a font that does not vary, and misreads a delta run that crosses from x into y. [testing](runbooks/testing.md)
* **FreeType versions differ on a damaged `gvar`**: 2.15 answers error 8 from `FT_Set_Var_Design_Coordinates`, 2.14 (Debian 13) from `FT_New_Face`. Both are a `TypographyException`. 26 passed on Homebrew PHP 8.4 NTS and ZTS, the Pi (FreeType 2.14) and Ubuntu 24.04 (2.13.2). [face](architecture/face.md)
