<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Support\Banner\BannerConfig;
use App\Support\HomePage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The editor's Preview button: the real home page, rendered by the live
 * templates with the banner being edited (nothing is saved). Opens in a new
 * tab without any editor controls.
 */
class BannerPreviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->validate(['config' => ['required', 'string', 'max:500000']]);

        $config = BannerConfig::normalise(json_decode($request->string('config'), true) ?: []);

        return response(view('home', ['isPreview' => true] + HomePage::data(null, $config))->render());
    }
}
