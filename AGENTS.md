# Agent guidance — jovian/venusian-freetype

1. **Read [`.okf/index.md`](.okf/index.md) first.** Open only the concepts the change touches.
2. **The contract is Topside's** (`Surface\Contracts\Typography\FontFace`, `Surface\Typography\Faces\FontFaceDriver`). Ask before assuming a change to it.
3. **FreeType's answer, not another driver's.** Every value comes from FreeType as it reads the file. Nothing here is made to match Lustrous or any other face driver; where they differ, FreeType's reading stands.
4. **Dependencies:** venusian/topside and ext-freetype only. Never another jovian package or Lustrous, not even in tests. No FFI.
5. **Glyph pixels are not this package's.** It gives outlines (geometry) only. Glyph bitmaps reach a renderer by address through ext-freetype, never as PHP strings.
6. **Tests** write their own fonts (`tests/Fixtures/TinyFont.php`), so every expected value sits in the test. A test names the behaviour it protects and fails when that logic is broken. Run them as [`.okf/runbooks/testing.md`](.okf/runbooks/testing.md) says: in a workbench, never the repo root.
7. **Leave nothing behind.** No `vendor/`, lock file, `composer.dev.json` or scratch file stays in the repo after a run; `.gitignore` hiding it does not make it gone.
8. **House files travel with the code.** A change that alters behaviour updates `README.md`, the `.okf` concept it touches (bump `generated.at`), `.okf/log.md`, and `SECURITY.md` when what the package reads or writes changes. `.github/workflows/tests.yml` runs the suite on GitHub.
9. **Version** 0.11.0, the framework's line.
