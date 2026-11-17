<?php

namespace Jovian\Typography\FreeType\Providers;

use Jovian\Typography\FreeType\FreeTypeFontFaceDriver;
use Voyager\NutsAndBolts\ServiceProvider;

class VenusianFreeTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        $this->registerTypographyDrivers();
    }

    /** 'freetype' on Typography's faces: picked by typography.face.default (TOPSIDE_FONT_FACE=freetype). */
    protected function registerTypographyDrivers(): void
    {
        app('typography.faces')->extend('freetype', fn () => new FreeTypeFontFaceDriver());
    }
}
