<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O bloco "Sobre nós" da home, que era texto fixo no Blade.
 *
 * O componente trazia tag, título, quatro parágrafos e o rótulo do botão
 * escritos à mão — e o mesmo texto saía em TODOS os sites da plataforma,
 * inclusive dizendo "a Corre Virtual é a comunidade que..." no site de outro
 * organizador. O botão, além disso, apontava para `href="#"`: não levava a
 * lugar nenhum desde sempre.
 *
 * As colunas ficam no próprio `organizers`: é um registro por organizador,
 * e uma tabela à parte para uma linha só seria um join a mais sem ganho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            // A tag pequena acima do título ("SOBRE A PLATAFORMA").
            $table->string('about_badge')->nullable()->after('active');
            $table->string('about_title')->nullable()->after('about_badge');
            $table->text('about_text')->nullable()->after('about_title');
            $table->string('about_button_label')->nullable()->after('about_text');
            $table->string('about_button_url')->nullable()->after('about_button_label');
        });

        // O texto que estava no Blade volta para quem ele descreve: o site da
        // Corre Virtual, que entra no ar hoje e não pode mudar de aparência por
        // causa desta migration. Os demais organizadores nascem vazios — o
        // bloco some no site deles até escreverem o próprio texto, o que é
        // melhor que herdar um texto que fala de outra empresa.
        $texto = <<<'TXT'
        Uma experiência completa de treinos e corridas: a mesma energia de prova, com a flexibilidade de correr no seu tempo, na sua rota favorita e no seu ritmo.

        Ideal para atletas de todos os níveis. Não importa se você está dando seus primeiros passos na corrida ou se já busca quebrar seus recordes pessoais, temos o desafio perfeito para você.

        Venha com amigos, família e seu time de treinos. A **Corre Virtual** é a comunidade que combina saúde, diversão e o sentimento único de conquista, enviando medalhas exclusivas direto para a sua casa.

        Transforme cada quilômetro em uma vitória. **Vamos juntos!**
        TXT;

        DB::table('organizers')
            ->where('domain', 'eventos.correvirtual.com.br')
            ->update([
                'about_badge' => 'SOBRE A PLATAFORMA',
                'about_title' => 'Corre Virtual - Desafie seus limites',
                'about_text' => $texto,
                'about_button_label' => 'COMEÇAR MEU DESAFIO',
                // O botão continua sem destino: ele nunca teve um. Quem
                // preencher a URL no painel faz o botão aparecer.
                'about_button_url' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->dropColumn([
                'about_badge',
                'about_title',
                'about_text',
                'about_button_label',
                'about_button_url',
            ]);
        });
    }
};
