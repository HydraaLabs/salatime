<?php

namespace App\Http\Controllers\Quran\Support;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class SupportController extends Controller
{
    public function privacyPolicy(): Response
    {
        return $this->publicView('support.privacy-policy');
    }

    public function termsCondition(): Response
    {
        return $this->publicView('support.terms-condition');
    }

    public function support(): Response
    {
        return $this->publicView('support.support');
    }

    private function publicView(string $view): Response
    {
        return response()
            ->view($view)
            ->header('Cache-Control', 'public, max-age=3600, s-maxage=3600')
            ->header('Vary', 'Accept-Encoding');
    }
}
