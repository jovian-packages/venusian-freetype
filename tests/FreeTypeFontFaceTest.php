<?php

declare(strict_types=1);

use Jovian\Typography\FreeType\FreeTypeFontFaceDriver;
use Jovian\Typography\FreeType\Tests\Fixtures\TinyFont;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypographyException;

/*
 * The FreeType face: each test writes a font holding known values and checks the face answers
 * exactly those, as FreeType reads them.
 */

beforeEach(function (): void {
    $this->directory = fontDirectory();
    $this->driver = new FreeTypeFontFaceDriver();
});

afterEach(function (): void {
    $this->driver->release();
});

it('reads the em and the glyph count, and scales the ascender, descender and line gap to a size', function (): void {
    $font = new TinyFont();   // hhea 800 / -200 / 90
    $font->unitsPerEm = 2000;
    $font->simple(500, []);
    $typo = new TinyFont();   // OS/2 typographic 700 / -300 / 50, used when USE_TYPO_METRICS is set
    $typo->useTypoMetrics = true;

    $face = $this->driver->face(new FontSource($font->write("{$this->directory}/face.ttf")));
    $metrics = $face->metrics(20);
    $typoMetrics = $this->driver->face(new FontSource($typo->write("{$this->directory}/typo.ttf")))->metrics(20);

    expect([$face->unitsPerEm(), $face->glyphCount()])->toBe([2000, 2])
        ->and([$metrics->ascent, $metrics->descent, $metrics->lineGap])->toEqualWithDelta([8.0, 2.0, 0.9], 1e-9)
        ->and([$typoMetrics->ascent, $typoMetrics->descent, $typoMetrics->lineGap])->toEqualWithDelta([14.0, 6.0, 1.0], 1e-9);
});

it('maps a codepoint to its glyph, and one the face lacks to glyph 0', function (): void {
    $font = new TinyFont();
    $a = $font->simple(500, []);
    $b = $font->simple(500, []);
    $font->map(0x41, $b)->map(0x4E2D, $a);
    $face = $this->driver->face(new FontSource($font->write("{$this->directory}/face.ttf")));

    expect([$face->glyph(0x41), $face->glyph(0x4E2D), $face->glyph(0x42)])->toBe([$b, $a, 0]);
});

it('gives each glyph its advance in font units, and a glyph the face does not have none', function (): void {
    $font = new TinyFont();
    $wide = $font->simple(600, [[[0, 0, true], [0, 700, true], [500, 700, true]]]);
    $narrow = $font->simple(250, []);
    $face = $this->driver->face(new FontSource($font->write("{$this->directory}/face.ttf")));

    expect([$face->advance($wide), $face->advance($narrow), $face->advance(3), $face->advance(-1)])->toBe([600, 250, 0, 0]);
});

it('walks a TrueType outline in font units: lines, quadratic curves, each contour closed', function (): void {
    $font = new TinyFont();
    $square = $font->simple(600, [[[0, 0, true], [0, 700, true], [500, 700, true], [500, 0, true]]]);
    $two = $font->simple(400, [[[0, 0, true], [200, 400, false], [400, 0, true]], [[100, 100, true], [150, 150, true], [200, 100, true]]]);
    $face = $this->driver->face(new FontSource($font->write("{$this->directory}/face.ttf")));

    expect($face->outline($square)->ops())->toBe([['M', 0.0, 0.0], ['L', 0.0, 700.0], ['L', 500.0, 700.0], ['L', 500.0, 0.0], ['L', 0.0, 0.0], ['Z']])
        ->and($face->outline($two)->ops())->toBe([
            ['M', 0.0, 0.0], ['Q', 200.0, 400.0, 400.0, 0.0], ['L', 0.0, 0.0], ['Z'],
            ['M', 100.0, 100.0], ['L', 150.0, 150.0], ['L', 200.0, 100.0], ['L', 100.0, 100.0], ['Z'],
        ])
        ->and($face->outline(0)->isEmpty())->toBeTrue()
        ->and(fn () => $face->outline(3))->toThrow(TypographyException::class, 'there is no glyph 3');
});

it('walks a CFF outline as cubic curves', function (): void {
    $font = new TinyFont();
    $font->simple(500, []);
    // glyph 1: 0 0 rmoveto, 100 0 0 100 -100 0 rrcurveto, endchar
    $font->cff = true;
    $font->cffTable = TinyFont::cffTableOf(["\x0E", "\x8B\x8B\x15\xEF\x8B\x8B\xEF\x27\x8B\x08\x0E"]);
    $face = $this->driver->face(new FontSource($font->write("{$this->directory}/face.otf")));

    expect($face->outline(1)->ops())->toBe([['M', 0.0, 0.0], ['C', 100.0, 0.0, 100.0, 100.0, 0.0, 100.0], ['L', 0.0, 0.0], ['Z']]);
});

it('opens a face of a collection by its index', function (): void {
    $regular = new TinyFont();
    $tall = new TinyFont();
    $tall->hhea = [900, -100, 0];
    $path = TinyFont::collection("{$this->directory}/pair.ttc", $regular, $tall);

    expect($this->driver->face(new FontSource($path, 1))->metrics(1000)->ascent)->toEqual(900.0)
        ->and($this->driver->face(new FontSource($path, 0))->metrics(1000)->ascent)->toEqual(800.0);
});

it('keeps one face a source until release(), after which the face refuses to be read', function (): void {
    $source = new FontSource((new TinyFont())->write("{$this->directory}/face.ttf"));
    $face = $this->driver->face($source);
    $same = $this->driver->face(new FontSource($source->path));

    $this->driver->release();

    expect($same)->toBe($face)
        ->and(fn () => $face->unitsPerEm())->toThrow(TypographyException::class, 'was released')
        ->and($this->driver->face($source)->unitsPerEm())->toBe(1000);
});

it('refuses a file that is not a font, and one that is missing, as a TypographyException', function (): void {
    file_put_contents("{$this->directory}/text.ttf", 'not a font at all');

    expect(fn () => $this->driver->face(new FontSource("{$this->directory}/text.ttf")))->toThrow(TypographyException::class, 'cannot open')
        ->and(fn () => $this->driver->face(new FontSource("{$this->directory}/none.ttf")))->toThrow(TypographyException::class, 'cannot open');
});
