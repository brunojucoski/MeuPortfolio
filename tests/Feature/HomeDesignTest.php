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
            ->assertSee('Sou artista')->assertSee('Sou cliente')
            ->assertSee('Cadastro artista')->assertSee('Cadastro de cliente')
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

    public function test_public_pages_use_cliente_in_visible_copy_and_accessible_labels(): void
    {
        foreach (['/', '/home', '/login', '/cadastro', '/cadastro?tipo=solicitante', '/sobre', '/termos-de-uso'] as $route) {
            $html = $this->get($route)->assertOk()->getContent();
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//script|//style') as $node) $node->parentNode->removeChild($node);
            $this->assertDoesNotMatchRegularExpression('/\bsolicitantes?\b/iu', $dom->textContent, $route);
            foreach ($xpath->query('//*[@aria-label or @alt or @placeholder or @data-title or @data-image-alt]') as $node) {
                foreach (['aria-label', 'alt', 'placeholder', 'data-title', 'data-image-alt'] as $attribute) {
                    $this->assertDoesNotMatchRegularExpression('/\bsolicitantes?\b/iu', $node->getAttribute($attribute), $route . ': ' . $attribute);
                }
            }
        }
        $this->get('/home')->assertSee('Conectando artistas e clientes em um só lugar');
        $this->get('/cadastro?tipo=solicitante')->assertSee('Cadastro de cliente')
            ->assertSee('value="solicitante"', false)->assertSee('aria-label="Cadastrar como cliente"', false);
    }

    public function test_configurable_role_copy_preserves_case_and_does_not_change_links_or_emails(): void
    {
        $source = 'Solicitante, solicitante, Solicitantes, solicitantes, SOLICITANTE e SOLICITANTES. https://example.test/solicitantes e solicitante@example.test';
        $expected = 'Cliente, cliente, Clientes, clientes, CLIENTE e CLIENTES. https://example.test/solicitantes e solicitante@example.test';
        $this->assertSame($expected, \App\Support\NomenclaturaPublica::clientes($source));
        $this->assertSame('Artes e clientes', \App\Support\NomenclaturaPublica::clientes('Artes e clientes'));
    }
}
