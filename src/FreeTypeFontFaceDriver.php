<?php

namespace Jovian\Typography\FreeType;

use FT_Library;
use Surface\Contracts\Typography\FontFace;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypographyException;
use Surface\Typography\Faces\FontFaceDriver;

/** 'freetype': faces FreeType opens, through ext-freetype. One FT_Library a driver, made on first use. */
class FreeTypeFontFaceDriver extends FontFaceDriver
{
    private ?FT_Library $library = null;

    /** @var array<string, FreeTypeFontFace> */
    private array $faces = [];

    /** @var array<string, \FT_Face> */
    private array $handles = [];

    public function driver(): string
    {
        return 'freetype';
    }

    /** @throws TypographyException When FreeType cannot start, or cannot open the source as a font. */
    public function face(FontSource $source): FontFace
    {
        $key = $source->key();
        if (isset($this->faces[$key])) {
            return $this->faces[$key];
        }
        if ($this->library === null) {
            $error = FT_Init_FreeType($library);
            if ($error !== 0 || $library === null) {
                throw new TypographyException("FreeType cannot start: error {$error}.");
            }
            $this->library = $library;
        }
        $error = FT_New_Face($this->library, $source->path, $source->index, $face);
        if ($error !== 0 || $face === null) {
            throw new TypographyException("FreeType cannot open {$key} as a font: error {$error}.");
        }
        try {
            $this->instance($face, $source);
        } catch (TypographyException $refused) {
            FT_Done_Face($face);

            throw $refused;
        }
        $this->handles[$key] = $face;

        return $this->faces[$key] = new FreeTypeFontFace($source, $face);
    }

    /**
     * Sets the instance a source names as the face's design coordinates, once: each source is its own
     * FT_Face. Every axis of the font gets a value, in the font's axis order: the source's, or the
     * axis's default. An axis the font lacks is not passed; FreeType clamps a value to its axis. A
     * font that does not vary is drawn as it is, as the other drivers draw it.
     *
     * @throws TypographyException When FreeType cannot read the axes of a variable font, or set them.
     */
    private function instance(\FT_Face $face, FontSource $source): void
    {
        if ($source->variations === [] || ($face->face_flags & FT_FACE_FLAG_MULTIPLE_MASTERS) === 0) {
            return;
        }
        if (! function_exists('FT_Get_MM_Var')) {
            throw new TypographyException("{$source->key()} is a variable font, and this ext-freetype build has no FT_Get_MM_Var: build ext-freetype from its current source.");
        }
        $error = FT_Get_MM_Var($face, $master);
        if ($error !== 0 || $master === null) {
            throw new TypographyException("FreeType cannot read the axes of {$source->key()}: error {$error}.");
        }
        $coordinates = [];
        foreach ($master->axis as $axis) {
            $tag = pack('N', $axis->tag);
            $coordinates[] = isset($source->variations[$tag])
                ? (int) round($source->variations[$tag] * 65536)
                : $axis->def;
        }
        FT_Done_MM_Var($this->library, $master);

        $error = FT_Set_Var_Design_Coordinates($face, count($coordinates), $coordinates);
        if ($error !== 0) {
            throw new TypographyException("FreeType cannot draw {$source->key()} at its coordinates: error {$error}.");
        }
    }

    public function release(): void
    {
        foreach ($this->faces as $key => $face) {
            $face->release();
            FT_Done_Face($this->handles[$key]);
        }
        $this->faces = $this->handles = [];
        if ($this->library !== null) {
            FT_Done_FreeType($this->library);
            $this->library = null;
        }
    }
}
