<?php

namespace App\Support;

use App\Models\PortfolioArtista;

class PortfolioSocialLinks
{
    public static function forProfile(?PortfolioArtista $portfolio, ?string $telefone): array
    {
        $links = [];
        foreach ([
            'instagram' => ['link_instagram', 'Instagram', 'bi-instagram'],
            'personal' => ['link_behance', 'Link pessoal', 'bi-link-45deg'],
            'tiktok' => ['link_tiktok', 'TikTok', 'bi-tiktok'],
            'github' => ['link_github', 'GitHub', 'bi-github'],
            'linkedin' => ['link_linkedin', 'LinkedIn', 'bi-linkedin'],
        ] as $network => [$field, $label, $icon]) {
            $url = trim((string) ($portfolio?->{$field} ?? ''));
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                continue;
            }
            $links[] = compact('network', 'label', 'icon', 'url');
        }

        $digits = preg_replace('/\D/', '', (string) $telefone);
        // Local Brazilian numbers include a two-digit area code, even when it is 55.
        if (in_array(strlen($digits), [10, 11], true)) {
            $digits = '55'.$digits;
        }
        if (preg_match('/^55\d{10,11}$/', $digits)) {
            $links[] = ['network' => 'whatsapp', 'label' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'url' => 'https://wa.me/'.$digits];
        }

        return $links;
    }
}
