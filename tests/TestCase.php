<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Statamic\Facades\URL;
use Statamic\Support\Arr;
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
        // Forgetting the whole singleton isn't safe here: Cascade also
        // carries a 'views' map used by getViewData() that's populated once
        // and expected to survive for the life of the instance, so
        // rebuilding it from scratch mid-request throws. Instead, once
        // routing has resolved the real request for this call, patch just
        // the request-derived cascade values in place — the same ones
        // Cascade::contextualVariables() computes from the request — and
        // leave everything else (views, sections, globals) untouched.
        Event::listen(RouteMatched::class, function (RouteMatched $event) {
            $request = $event->request;
            $cascade = app(Cascade::class);

            $cascade->withRequest($request);
            $cascade->set('current_url', $request->url());
            $cascade->set('current_full_url', $request->fullUrl());
            $cascade->set('current_uri', URL::tidy($request->path()));
            $cascade->set('get_post', Arr::sanitize($request->all()));
            $cascade->set('get', Arr::sanitize($request->query->all()));
            $cascade->set('post', $request->isMethod('post') ? Arr::sanitize($request->request->all()) : []);
        });
    }
}
