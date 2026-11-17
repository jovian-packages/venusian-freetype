<?php

declare(strict_types=1);

use Jovian\Typography\FreeType\FreeTypeFontFaceDriver;
use Jovian\Typography\FreeType\Tests\Fixtures\TinyFont;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypographyException;

/*
 * The FreeType face drawing a variable font at the instance its source names. The fonts have two
 * axes, in this order: wght 100..400..900 and wdth 50..100..200. So wght 650 is half way to the
 * wght maximum, 900 all the way; wdth 150 is half way to the wdth maximum.
 */

beforeEach(function (): void {
    $this->directory = fontDirectory();
    $this->driver = new FreeTypeFontFaceDriver();
});

afterEach(function (): void {
    $this->driver->release();
});

/**
 * A variable font whose glyph 1 is a 100,0 - 300,200 square, 600 wide. Its first point moves +10 in
 * x at the wght maximum and -40 at the wght minimum, and +20 in y at the wdth maximum.
 */
function variableSquareFont(): TinyFont
{
    $font = new TinyFont();
    $font->axes = [['wght', 100, 400, 900], ['wdth', 50, 100, 200]];
    $square = $font->simple(600, [[[100, 0, true], [300, 0, true], [300, 200, true], [100, 200, true]]]);
    // four points, then the four phantom points
    $first = fn (int $dx, int $dy): array => [[$dx, $dy], [0, 0], [0, 0], [0, 0], [0, 0], [0, 0], [0, 0], [0, 0]];
    $font->vary($square, [
        ['peak' => [1, 0], 'points' => 'all', 'deltas' => $first(10, 0)],
        ['peak' => [-1, 0], 'points' => 'all', 'deltas' => $first(-40, 0)],
        ['peak' => [0, 1], 'points' => 'all', 'deltas' => $first(0, 20)],
    ]);

    return $font;
}

it('draws the outline at the source\'s coordinates, each given to its own axis, the others at their default', function (array $variations, array $start): void {
    $path = variableSquareFont()->write("{$this->directory}/variable.ttf");
    $face = $this->driver->face(new FontSource($path, 0, $variations));

    expect(array_slice($face->outline(1)->ops()[0], 1))->toBe($start);
})->with([
    'no coordinates: the default' => [[], [100.0, 0.0]],
    'wght 650: half of +10' => [['wght' => 650], [105.0, 0.0]],
    'wght 900: all of +10' => [['wght' => 900], [110.0, 0.0]],
    'wght 250: half of -40' => [['wght' => 250], [80.0, 0.0]],
    'wght 2000: clamped to its maximum' => [['wght' => 2000], [110.0, 0.0]],
    'wdth alone: wght stays at 400' => [['wdth' => 200], [100.0, 20.0]],
    'wght and wdth, sorted the other way round in the source' => [['wght' => 900, 'wdth' => 150], [110.0, 10.0]],
    'an axis the font lacks is not passed' => [['XOPQ' => 50, 'wght' => 900], [110.0, 0.0]],
]);

it('draws the advance at the source\'s coordinates through HVAR', function (array $variations, int $advance): void {
    $font = variableSquareFont();
    // one region, the wght maximum; one row a glyph: glyph 1 is 100 wider there
    $font->hvar = TinyFont::hvarTable(TinyFont::store([[[0, 1, 1], [0, 0, 0]]], [['regions' => [0], 'words' => 1, 'rows' => [[0], [100]]]]));
    $face = $this->driver->face(new FontSource($font->write("{$this->directory}/variable.ttf"), 0, $variations));

    expect($face->advance(1))->toBe($advance);
})->with([
    'the default' => [[], 600],
    'wght 650' => [['wght' => 650], 650],
    'wght 900' => [['wght' => 900], 700],
]);

it('draws the ascender at the source\'s coordinates through MVAR, on whichever metrics the face uses', function (bool $typo, array $variations, float $ascent): void {
    $font = variableSquareFont();
    $font->useTypoMetrics = $typo;   // typographic ascender 700, 'hhea' 800
    $font->mvar = TinyFont::mvarTable(TinyFont::store([[[0, 1, 1], [0, 0, 0]]], [['regions' => [0], 'words' => 1, 'rows' => [[100]]]]), ['hasc' => [0, 0]]);
    $face = $this->driver->face(new FontSource($font->write("{$this->directory}/variable.ttf"), 0, $variations));

    expect($face->metrics(1000)->ascent)->toEqual($ascent);
})->with([
    'typographic, the default' => [true, [], 700.0],
    'typographic, wght 650' => [true, ['wght' => 650], 750.0],
    'typographic, wght 900' => [true, ['wght' => 900], 800.0],
    // FreeType adds 'hasc' to the 'hhea' ascender too
    'hhea, wght 900' => [false, ['wght' => 900], 900.0],
]);

it('keeps two instances of one file apart, each its own face', function (): void {
    $path = variableSquareFont()->write("{$this->directory}/variable.ttf");
    $heavy = $this->driver->face(new FontSource($path, 0, ['wght' => 900]));
    $light = $this->driver->face(new FontSource($path, 0, ['wght' => 250]));
    $default = $this->driver->face(new FontSource($path));

    expect($heavy->outline(1)->ops()[0][1])->toEqual(110.0)
        ->and($light->outline(1)->ops()[0][1])->toEqual(80.0)
        ->and($default->outline(1)->ops()[0][1])->toEqual(100.0)
        ->and($this->driver->face(new FontSource($path, 0, ['wght' => 900])))->toBe($heavy);
});

it('throws a TypographyException when FreeType cannot draw a source at its coordinates', function (): void {
    $path = variableSquareFont()->write("{$this->directory}/damaged.ttf");
    // every byte of 'gvar' 0xFF: FreeType answers FT_Err_Invalid_Table when the coordinates are set
    $bytes = file_get_contents($path);
    for ($i = 0; $i < unpack('n', $bytes, 4)[1]; $i++) {
        if (substr($bytes, 12 + 16 * $i, 4) === 'gvar') {
            [1 => $at, 2 => $length] = unpack('N2', $bytes, 12 + 16 * $i + 8);
            $bytes = substr_replace($bytes, str_repeat("\xFF", $length), $at, $length);
        }
    }
    file_put_contents($path, $bytes);

    // FreeType 2.15 refuses the table when the coordinates are set; 2.14 (Debian 13) already when it opens
    try {
        $this->driver->face(new FontSource($path, 0, ['wght' => 900]));
        $this->fail('A damaged gvar opened.');
    } catch (TypographyException $refused) {
        expect($refused->getMessage())->toMatch('/^FreeType cannot (draw|open) '.preg_quote("{$path}#0@wght=900", '/').' (at its coordinates|as a font): error 8\.$/');
    }
});

it('draws a font that does not vary as it is, whatever coordinates its source names', function (): void {
    $font = new TinyFont();
    $font->simple(600, [[[100, 0, true], [300, 0, true], [300, 200, true], [100, 200, true]]]);
    $face = $this->driver->face(new FontSource($font->write("{$this->directory}/static.ttf"), 0, ['wght' => 900]));

    expect($face->outline(1)->ops()[0])->toEqual(['M', 100.0, 0.0])
        ->and($face->advance(1))->toBe(600);
});
