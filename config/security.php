<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Sent by App\Http\Middleware\SecurityHeaders. The enforced policy is
    | deliberately partial: Filament, Livewire and Alpine rely on inline
    | scripts and styles, and Alpine's standard build needs 'unsafe-eval', so
    | a script-src would either break the panel or protect nothing. What is
    | enforced still matters: no framing by other sites, no <base> hijacking,
    | no plugins, and forms can only post back to us.
    |
    | A fuller policy can be tried out first in report-only mode, which the
    | browser checks and reports on without blocking anything. Empty = off.
    |
    */

    'csp' => env('SECURITY_CSP', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'"),

    'csp_report_only' => env('SECURITY_CSP_REPORT_ONLY', ''),

];
