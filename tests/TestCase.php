<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Statamic\View\Cascade;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Statamic's Cascade is bound as a container singleton
        // (Statamic\Providers\ViewServiceProvider) and reads the request it
        // was constructed with. In this test harness that construction
        // happens during createApplication()'s console kernel bootstrap,
        // before $this->get() ever dispatches the real (fake) HTTP request
        // through the kernel — so query-string-derived cascade values like
        // {{ get:range }} would otherwise always read as empty, even for a
        // genuine `$this->get('/path?x=y')` call, because nothing in
        // Statamic ever calls Cascade::withRequest() to refresh it.
        //
        // The fix only needs to swap the request reference, not recompute
        // any data by hand: Statamic\View\View::cascade() calls
        // Cascade::instance()->hydrate() once per front-end response, which
        // runs well after routing (and thus after this listener), and
        // hydrate() clears and re-derives every contextual variable —
        // including 'get', 'current_url', etc. — from whatever request
        // Cascade currently holds. So by the time hydrate() runs, it
        // recomputes those keys itself from the request we swapped in here;
        // setting them ourselves would just be overwritten moments later.
        Event::listen(RouteMatched::class, function (RouteMatched $event) {
            app(Cascade::class)->withRequest($event->request);
        });
    }
}
