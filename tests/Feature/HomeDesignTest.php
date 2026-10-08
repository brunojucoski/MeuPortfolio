<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeDesignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    public function test_home_uses_shared_lever_and_keeps_registration_links(): void
    {
        $response = $this->get('/home')->assertOk();
        $response->assertSee('css/lever-switch.css', false)
            ->assertSee('home-audience-toggle lever-switch', false)
            ->assertSee('id="home-audience-switch"', false)
            ->assertSee('aria-controls="conteudoArtista conteudoContratante"', false)
            ->assertSee('Sou artista')->assertSee('Sou solicitante')
            ->assertSee('Cadastro artista')->assertSee('Cadastro solicitante')
            ->assertSee('js/home.js', false)
            ->assertDontSee('id="btnArtista"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="home-audience-switch"'));
    }

    public function test_both_home_routes_render_aurora_only_inside_hero(): void
    {
        foreach (['/', '/home'] as $route) {
            $html = $this->get($route)->assertOk()->getContent();
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(16, $xpath->query('//*[@data-aurora-bars]//*[contains(concat(" ", normalize-space(@class), " "), " home-aurora-bar ")]')->length);
            $this->assertSame(1, $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " home-hero ")]//*[@data-aurora-bars]')->length);
            $this->assertSame(1, $xpath->query('//*[@data-aurora-bars]')->length);
        }
    }
}
