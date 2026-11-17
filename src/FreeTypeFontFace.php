<?php

namespace Jovian\Typography\FreeType;

use FT_Face;
use FT_Outline_Funcs;
use FT_Vector;
use Surface\Contracts\Typography\FontFace;
use Surface\Contracts\Typography\FontMetrics;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypographyException;
use Surface\NutsAndBolts\Path;

/**
 * A face FreeType opened: what FreeType reads from the file, as FreeType reads it. Metrics are the
 * face's ascender, descender and height; glyphs come from the character map FreeType selected;
 * advances and outlines are loaded unscaled (FT_LOAD_NO_SCALE), so they are in font units. FreeType
 * reads TrueType and CFF outlines: a CFF glyph's curves are cubic.
 */
final class FreeTypeFontFace implements FontFace
{
    private bool $released = false;

    public function __construct(
        private readonly FontSource $source,
        private readonly FT_Face $face,
    ) {}

    public function source(): FontSource
    {
        return $this->source;
    }

    public function unitsPerEm(): int
    {
        return $this->open()->units_per_EM;
    }

    public function glyphCount(): int
    {
        return $this->open()->num_glyphs;
    }

    public function metrics(float $size): FontMetrics
    {
        $face = $this->open();
        $scale = $size / $face->units_per_EM;

        // height is FreeType's baseline-to-baseline distance: ascender - descender + line gap
        return new FontMetrics(
            $face->ascender * $scale,
            -$face->descender * $scale,
            ($face->height - $face->ascender + $face->descender) * $scale,
        );
    }

    public function glyph(int $codepoint): int
    {
        return FT_Get_Char_Index($this->open(), $codepoint);
    }

    /** @throws TypographyException When FreeType cannot load the glyph. */
    public function advance(int $glyph): int
    {
        $face = $this->open();
        if ($glyph < 0 || $glyph >= $face->num_glyphs) {
            return 0;
        }

        return $this->load($face, $glyph)->advance->x;
    }

    public function outline(int $glyph): Path
    {
        $face = $this->open();
        $path = new Path();
        if ($glyph < 0 || $glyph >= $face->num_glyphs) {
            throw new TypographyException("{$this->source->key()} has {$face->num_glyphs} glyphs; there is no glyph {$glyph}.");
        }
        $slot = $this->load($face, $glyph);

        $open = false;
        $funcs = new FT_Outline_Funcs();
        $funcs->move_to = function (FT_Vector $to) use ($path, &$open): int {
            if ($open) {
                $path->close();
            }
            $path->moveTo($to->x, $to->y);
            $open = true;

            return 0;
        };
        $funcs->line_to = function (FT_Vector $to) use ($path): int {
            $path->lineTo($to->x, $to->y);

            return 0;
        };
        $funcs->conic_to = function (FT_Vector $control, FT_Vector $to) use ($path): int {
            $path->quadTo($control->x, $control->y, $to->x, $to->y);

            return 0;
        };
        $funcs->cubic_to = function (FT_Vector $first, FT_Vector $second, FT_Vector $to) use ($path): int {
            $path->cubicTo($first->x, $first->y, $second->x, $second->y, $to->x, $to->y);

            return 0;
        };
        $error = FT_Outline_Decompose($slot->outline, $funcs, null);
        if ($error !== 0) {
            throw new TypographyException("FreeType cannot walk glyph {$glyph} of {$this->source->key()}: error {$error}.");
        }
        if ($open) {
            $path->close();
        }

        return $path;
    }

    /** Its driver closed it. */
    public function release(): void
    {
        $this->released = true;
    }

    /** @throws TypographyException When its driver released it. */
    private function open(): FT_Face
    {
        if ($this->released) {
            throw new TypographyException("{$this->source->key()} was released; open it again through its driver.");
        }

        return $this->face;
    }

    /** @throws TypographyException When FreeType cannot load the glyph. */
    private function load(FT_Face $face, int $glyph): \FT_GlyphSlot
    {
        $error = FT_Load_Glyph($face, $glyph, FT_LOAD_NO_SCALE);
        if ($error !== 0) {
            throw new TypographyException("FreeType cannot load glyph {$glyph} of {$this->source->key()}: error {$error}.");
        }

        return $face->glyph;
    }
}
