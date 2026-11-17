# Agent guidance — jovian/venusian-freetype

1. **The contract is Topside's** (`Surface\Contracts\Typography\FontFace`, `Surface\Typography\Faces\FontFaceDriver`). Ask before assuming a change to it.
2. **FreeType's answer, not another driver's.** Every value comes from FreeType as it reads the file. Nothing here is made to match Lustrous or any other face driver; where they differ, FreeType's reading stands.
3. **Dependencies:** venusian/topside and ext-freetype only. Never another jovian package or Lustrous, not even in tests. No FFI.
4. **Glyph pixels are not this package's.** It gives outlines (geometry) only. Glyph bitmaps reach a renderer by address through ext-freetype, never as PHP strings.
5. **Tests** write their own fonts (`tests/Fixtures/TinyFont.php`), so every expected value sits in the test. A test names the behaviour it protects and fails when that logic is broken.
6. **Version** 0.11.0, the framework's line.
