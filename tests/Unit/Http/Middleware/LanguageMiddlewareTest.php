<?php

namespace Pterodactyl\Tests\Unit\Http\Middleware;

use Mockery as m;
use Mockery\MockInterface;
use Illuminate\Foundation\Application;
use Pterodactyl\Http\Middleware\LanguageMiddleware;

class LanguageMiddlewareTest extends MiddlewareTestCase
{
    private MockInterface $appMock;

    /**
     * Setup tests.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->appMock = m::mock(Application::class);
    }

    public function testPortugueseBrowserReceivesPortuguese()
    {
        $this->request->shouldReceive('getPreferredLanguage')->with(['pt', 'en'])->once()->andReturn('pt');
        $this->appMock->shouldReceive('setLocale')->with('pt')->once()->andReturnNull();

        $this->getMiddleware()->handle($this->request, $this->getClosureAssertions());
    }

    public function testEnglishBrowserReceivesEnglish()
    {
        $this->request->shouldReceive('getPreferredLanguage')->with(['pt', 'en'])->once()->andReturn('en');
        $this->appMock->shouldReceive('setLocale')->with('en')->once()->andReturnNull();

        $this->getMiddleware()->handle($this->request, $this->getClosureAssertions());
    }

    public function testUnsupportedBrowserLanguageFallsBackToEnglish()
    {
        $this->request->shouldReceive('getPreferredLanguage')->with(['pt', 'en'])->once()->andReturnNull();
        $this->appMock->shouldReceive('setLocale')->with('en')->once()->andReturnNull();

        $this->getMiddleware()->handle($this->request, $this->getClosureAssertions());
    }

    /**
     * Return an instance of the middleware using mocked dependencies.
     */
    private function getMiddleware(): LanguageMiddleware
    {
        return new LanguageMiddleware($this->appMock);
    }
}
