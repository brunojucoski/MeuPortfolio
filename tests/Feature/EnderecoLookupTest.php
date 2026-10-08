<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnderecoLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'services.nominatim.url' => 'https://nominatim.example.test']);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_search_is_public_identified_restricted_to_brazil_and_cached(): void
    {
        Http::fake(['nominatim.example.test/*' => Http::response([['lat' => '-28.67', 'lon' => '-49.36']])]);
        $url = route('endereco.localizar', ['q' => 'Rua de teste, Criciuma, SC, Brasil']);
        $this->getJson($url)->assertOk()->assertJsonPath('0.lat', '-28.67');
        $this->getJson($url)->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['countrycodes'] === 'br' && $request['limit'] === 1
            && str_starts_with($request->header('User-Agent')[0], 'MeuPortfolio/1.0'));
    }

    public function test_reverse_lookup_preserves_address_data_and_is_cached(): void
    {
        Http::fake(['nominatim.example.test/*' => Http::response(['address' => ['road' => 'Rua de teste', 'city' => 'Criciuma']])]);
        $url = route('endereco.reverso', ['lat' => -28.67, 'lon' => -49.36]);
        $this->getJson($url)->assertOk()->assertJsonPath('address.city', 'Criciuma');
        $this->getJson($url)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_global_limit_is_shared_between_search_and_reverse(): void
    {
        Http::fake(['*' => Http::response([])]);
        $this->getJson(route('endereco.localizar', ['q' => 'Rua de teste, Criciuma']))->assertOk();
        $this->getJson(route('endereco.reverso', ['lat' => -28, 'lon' => -49]))->assertStatus(429)->assertHeader('Retry-After', '1');
        Http::assertSentCount(1);
    }

    public function test_provider_failure_releases_lock_and_does_not_cache_failure(): void
    {
        Http::fake(['*' => Http::sequence()->push([], 500)->push([])]);
        $url = route('endereco.localizar', ['q' => 'Rua de teste, Criciuma']);
        $this->getJson($url)->assertStatus(503);
        Cache::forget('endereco:last-request');
        $this->getJson($url)->assertOk()->assertExactJson([]);
    }

    public function test_invalid_coordinates_and_queries_do_not_call_provider(): void
    {
        $this->getJson(route('endereco.reverso', ['lat' => 91, 'lon' => -181]))->assertUnprocessable();
        $this->getJson(route('endereco.localizar', ['q' => str_repeat('x', 301)]))->assertUnprocessable();
        $this->getJson(route('endereco.localizar'))->assertUnprocessable();
        Http::assertNothingSent();
    }
}
