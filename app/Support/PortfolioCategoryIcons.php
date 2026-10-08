<?php

namespace App\Support;

class PortfolioCategoryIcons
{
    public static function all(): array
    {
        $icons = [];
        foreach ([
            'palette' => 'Paleta', 'brush' => 'Pincel', 'pencil' => 'Lápis',
            'camera' => 'Câmera', 'camera-reels' => 'Cinema', 'image' => 'Imagem',
            'music-note-beamed' => 'Música', 'mic' => 'Microfone', 'headphones' => 'Fones',
            'film' => 'Filme', 'vector-pen' => 'Design', 'scissors' => 'Tesoura',
            'flower1' => 'Flor', 'heart' => 'Coração', 'star' => 'Estrela',
            'lightning' => 'Raio', 'gem' => 'Joia', 'easel' => 'Cavalete',
            'controller' => 'Jogos', 'megaphone' => 'Megafone', 'book' => 'Livro',
        ] as $name => $label) {
            $icons['bi-'.$name] = ['nome' => $label, 'grupo' => 'Clássicos'];
        }
        foreach ([
            'camera' => 'Câmera', 'image' => 'Imagem', 'heart' => 'Coração',
            'image-gallery' => 'Galeria', 'paint-bucket' => 'Tinta', 'music' => 'Música',
            'headphone' => 'Fones', 'gamepad' => 'Jogos', 'coffee' => 'Café',
            'lightbulb' => 'Ideia', 'edit' => 'Lápis', 'trophy' => 'Troféu',
        ] as $name => $label) {
            $icons['pixel-'.$name] = ['nome' => $label, 'grupo' => 'Pixel art'];
        }

        return $icons;
    }
}
