<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Http\Middleware\SetUserLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->viteTheme('resources/css/filament/app/theme.css')
            ->login()
            ->registration()
            ->profile(EditProfile::class, isSimple: false)
            ->brandName('Vispa')
            ->brandLogo(fn () => view('filament.brand'))
            ->font('Karla')
            ->serifFont('Fraunces')
            ->viteTheme('resources/css/filament/app/theme.css')
            ->colors([
                // Hand-tuned rather than Color::hex(): the generator keeps only the hue and
                // forces its own lightness curve, which lands shade 400 — the shade Filament
                // fills solid buttons with — on a pastel pink. Here 400 is the mockup's
                // raspberry exactly, and the rest of the ramp is built around it.
                'primary' => [
                    50 => 'oklch(0.9750 0.0150 3.77)',
                    100 => 'oklch(0.9450 0.0360 3.77)',
                    200 => 'oklch(0.8930 0.0760 3.77)',
                    300 => 'oklch(0.8000 0.1450 3.77)',
                    400 => 'oklch(0.5882 0.2223 3.77)',
                    500 => 'oklch(0.5450 0.2100 3.77)',
                    600 => 'oklch(0.4950 0.1900 3.77)',
                    700 => 'oklch(0.4400 0.1650 3.77)',
                    800 => 'oklch(0.3850 0.1380 3.77)',
                    900 => 'oklch(0.3400 0.1120 3.77)',
                    950 => 'oklch(0.2450 0.0780 3.77)',
                ],
                'info' => Color::hex('#1f9fb5'),      // teal, for links and the source callout
                'success' => Color::hex('#4d8027'),   // herb green
                'warning' => Color::hex('#b8701a'),
                // The gray ramp is deliberately absent: it is set in the theme's @theme block so
                // the whole panel picks up Vispa's green-biased neutrals. A 'gray' entry here
                // would be injected inline and win over it.
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // After Authenticate, so the user is resolved and their language choice applies.
                SetUserLocale::class,
            ]);
    }
}
