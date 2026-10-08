<?php



namespace Database\Seeders;
use Database\Seeders\tipo_usuario;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{

public function run(): void
{
    $this->call([
        tipo_usuario::class,
    ]);
}
    
}
