<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use App\Support\MailIdentity;
use App\Models\ProductReview;
use App\Observers\ProductReviewObserver;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // Manually register Spatie middleware
        $this->app->singleton('role', function () {
            return new \Spatie\Permission\Middleware\RoleMiddleware();
        });

        $this->app->singleton('permission', function () {
            return new \Spatie\Permission\Middleware\PermissionMiddleware();
        });

        $this->app->singleton('role_or_permission', function () {
            return new \Spatie\Permission\Middleware\RoleOrPermissionMiddleware();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Carbon::setLocale(config('app.locale'));
        Route::model('role', \Spatie\Permission\Models\Role::class);

        // Keep product rating stats in sync whenever a review changes.
        ProductReview::observe(ProductReviewObserver::class);

        $this->configureRateLimiters();
        $this->normalizeMailReplyToConfig();
        $this->configureTransactionalMailHeaders();
    }

    protected function normalizeMailReplyToConfig(): void
    {
        $fromAddress = config('mail.from.address');
        $replyAddress = config('mail.reply_to.address');

        if (!is_string($replyAddress) || !filter_var($replyAddress, FILTER_VALIDATE_EMAIL)) {
            if (is_string($fromAddress) && filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                Config::set('mail.reply_to.address', $fromAddress);
            }
        }
    }

    protected function configureTransactionalMailHeaders(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $headers = $event->message->getHeaders();
            if (!$headers->has('X-Mailer')) {
                $headers->addTextHeader('X-Mailer', config('app.name') . ' Mailer');
            }
            if (!$headers->has('X-Auto-Response-Suppress')) {
                $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
            }
        });
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('reviews', function (Request $request) {
            return Limit::perMinute(20)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('follows', function (Request $request) {
            return Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}