<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        // mensagem was already TEXT when notificacoes was created.
    }

    public function down()
    {
        // Do not narrow an existing TEXT column and risk truncating notifications.
    }
};
