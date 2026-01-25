<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use App\Models\Card;
use App\Models\Deck;
use App\Models\Export;
use App\Models\ExtractionJob;
use App\Models\JobPage;
use App\Policies\CardPolicy;
use App\Policies\DeckPolicy;
use App\Policies\ExportPolicy;
use App\Policies\ExtractionJobPolicy;
use App\Policies\JobPagePolicy;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::policy(Deck::class, DeckPolicy::class);
        Gate::policy(Card::class, CardPolicy::class);
        Gate::policy(ExtractionJob::class, ExtractionJobPolicy::class);
        Gate::policy(JobPage::class, JobPagePolicy::class);
        Gate::policy(Export::class, ExportPolicy::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
