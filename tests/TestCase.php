<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * No test may reach the network.
     *
     * Adding a paper by DOI or URL calls Crossref, then OpenAlex, then
     * DataCite, then possibly arXiv and finally the article page itself; the
     * AI layer calls a model provider. Left real, those make the suite slow,
     * flaky and dependent on someone else's uptime.
     *
     * Rather than stubbing defaults here — Laravel gives precedence to the
     * first registered stub, so a default would silently override a test's own
     * Http::fake() — any request a test has not explicitly faked simply throws.
     * That is what caught a metadata test quietly fetching a live arxiv.org
     * page because only export.arxiv.org had been stubbed.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
