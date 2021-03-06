<?php

namespace App\Http\Controllers;

use App;
use App\Recurso;
use App\Reserva;
use Carbon\Carbon;
use Illuminate\Session\CacheBasedSessionHandler;

class DashboardController extends Controller
{
    /**
     * Redireciona o usuário para a rota conrrespondente a sua dashboard
     */
    public function getHome()
    {
        if(auth()->user()->isAdmin()) $route = 'dashboard.show.administrador';
        else $route = 'dashboard.show.regular';

        return redirect()->route($route);
    }

    /**
     * Recupera informações para a view dashboard de administração
     */
    public function getAdministradorHome()
    {

        Carbon::setLocale('pt_BR'); // Altera o Carbon para trabalhar com a língua portuguesa

        // Obtenção de datas
        $mesAtual = Carbon::now()->format('Y-m-d');
        $mesPassado = Carbon::now()->subMonth()->format('Y-m-d');
        $mesRetrasado = Carbon::now()->subMonths(2)->format('Y-m-d');
        $seisMesesAtras = Carbon::now()->subMonths(6)->format('Y-m-d');

        // Obtenção das reservas para widgets e gráficos
        $reservas = Reserva::with(['usuario', 'recurso'])->iniciandoEm($seisMesesAtras)->get();
        $reservasMesAtual = $reservas->filter(function ($reserva) use($mesAtual, $mesPassado) {
            return ($reserva->data <= $mesAtual) && ($reserva->data >= $mesPassado) ;
        });
        $reservasMesPassado = $reservas->filter(function ($reserva) use($mesPassado, $mesRetrasado) {
            return ($reserva->data >= $mesRetrasado) && ($reserva->data <= $mesPassado);
        });

        /* Mês Atual */

        // 3 usuários com mais reservas nesse mês
        $reservasPorUsuario = $reservasMesAtual->groupBy('usuario.id')->sort()->reverse()->take(3);
        $topUsuariosMesAtual = array();
        foreach($reservasPorUsuario as $reserva) $topUsuariosMesAtual[$reserva[0]->usuario->nome] = $reserva->count();

        // 3 recursos com mais reservas nesse mês
        $reservasPorRecurso = $reservasMesAtual->groupBy('recurso.nome')->sort()->reverse()->take(3);
        $topRecursosMesAtual = array();
        foreach($reservasPorRecurso->keys() as $recurso)
            $topRecursosMesAtual[$recurso] = $reservasPorRecurso[$recurso]->count();

        /* Mês Passado */

        // 3 usuários com mais reservas nesse mês
        $reservasPorUsuario = $reservasMesPassado->groupBy('usuario.id')->sort()->reverse()->take(3);
        $topUsuariosMesPassado = array();
        foreach($reservasPorUsuario as $reserva)
            $topUsuariosMesPassado[$reserva[0]->usuario->nome] = $reserva->count();

        // 3 recursos com mais reservas nesse mês
        $reservasPorRecurso = $reservasMesPassado->groupBy('recurso.nome')->sort()->reverse()->take(3);
        $topRecursosMesPassado = array();
        foreach($reservasPorRecurso->keys() as $recurso)
            $topRecursosMesPassado[$recurso] = $reservasPorRecurso[$recurso]->count();

        /* Reservas e uso de recursos nos últimos 6 meses */
        // Reservas
        $reservasSemestrePassado = $reservas->groupBy(function ($item) {
            return Carbon::createFromFormat('Y-m-d', $item->data)->month;
        });
        $meses = $reservasSemestrePassado->keys();
        $ultimasReservas = array();
        foreach ($meses as $mes) $ultimasReservas[$this->nomeMes($mes)] = $reservasSemestrePassado[$mes]->count();

        // Uso de recursos
        $recursosSemestrePassado = $reservas->groupBy('recurso.nome');
        $recursos = $recursosSemestrePassado->keys();
        $ultimosRecursos = array();
        foreach ($recursos as $recurso) $ultimosRecursos[$recurso] = $recursosSemestrePassado[$recurso]->count();

        /* Criação dos gráficos */
        $graficos = array();
        $graficos['recursos'] = $this->gerarGraficoDeUsoDeRecursos($ultimosRecursos);
        $graficos['reservas'] = $this->gerarGraficoDeUsoSobreTempo($ultimasReservas);

        return view("dashboard.usuario.administrador", compact('graficos'))->with([
            'topUsuariosMesAtual' => $topUsuariosMesAtual,
            'topRecursosMesAtual' => $topRecursosMesAtual,
            'topUsuariosMesPassado' => $topUsuariosMesPassado,
            'topRecursosMesPassado' => $topRecursosMesPassado
        ]);
    }

    /**
     * Recupera informações para a view de usuário regular
     */
    public function getRegularHome()
    {
        Carbon::setLocale('pt_BR'); // Altera o Carbon para trabalhar com a língua portuguesa

        $mesAtual = Carbon::now()->subMonth()->format('Y-m-d');
        $mesPassado = Carbon::now()->subMonths(2)->format('Y-m-d');

        // Todas as reservas feitas pelo usuário
        $todasReservas = auth()->user()->reservas()->get();
        $todasReservas->load('recurso');

        // proximas reservas
        $proximasReservas = $todasReservas->filter(function ($reserva) {
            return $reserva->data >= Carbon::now()->format('Y-m-d');
        });

        // 5 tipos de reservas mais frequentes de todos os tempos
        $reservasFrequentes = $todasReservas->groupBy('recurso.nome')->sort()->reverse()->take(5);

        /* Mês atual */
        // Todas as reservas deste mês
        $todasReservasMesAtual = $recursoMaisAlocadoMesAtual = $todasReservas->filter(function($reserva){
            return $reserva->data >= Carbon::now()->subMonth()->format('Y-m-d');
        });

        // Recurso mais alocado no mês atual
        $recursoMaisAlocadoMesAtual = $todasReservasMesAtual->sort()->reverse()->first();

        // Reservas ativas esse mes
        $reservasMes = $todasReservasMesAtual->count();


        /* Mês passado */
        // Todas as reservas do mês passado
        $todasReservasMesPassado = $todasReservas->filter(function ($reserva) use ($mesAtual, $mesPassado){
            return ($reserva->data < $mesAtual) && ($reserva->data >= $mesPassado);
        });

        // Recurso mais alocado no més passado
        $recursoMaisAlocadoMesPassado = $todasReservasMesPassado->sort()->reverse()->first();

        // Reservas ativas messado
        $reservasMesPassado = $todasReservasMesPassado->count();

        return view('dashboard.usuario.regular')->with([
            'topRecursoMesAtual' => $recursoMaisAlocadoMesAtual,
            'reservasMesAtual' => $reservasMes,
            'reservasMesPassado' => $reservasMesPassado,
            'topRecursoMesPassado' => $recursoMaisAlocadoMesPassado,
            'proximasReservas' => $proximasReservas,
            'reservasFrequentes' => $reservasFrequentes
        ]);
    }

    /**
     * Renderiza a view com informações sobre o sistema.
     */
    public function showAbout()
    {
        return view("about");
    }

    /**
     * Obtém o nome do mês de acordo com a sua ordem
     * @param int $numeroMes Número correspondente ao mês
     * @return string Nome do mês
     */
    private function nomeMes($numeroMes)
    {
        switch ($numeroMes)
        {
            case 1:
                return "Janeiro";
            case 2:
                return "Fevereiro";
            case 3:
                return "Março";
            case 4:
                return "Abril";
            case 5;
                return "Maio";
            case 6:
                return "Junho";
            case 7:
                return "Julho";
            case 8:
                return "Agosto";
            case 9:
                return "Setembro";
            case 10:
                return "Outubro";
            case 11:
                return "Novembro";
            case 12:
                return "Dezembro";
            default:
                return "Não existe";
        }
    }

    /**
     * Gera um cor aleatória no formato Hex.
     * @return string Cor aleatória no formato Hex
     */
    private function gerarCor()
    {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Gera o gráfico de pizza do uso dos recursos.
     * @param array $recursos Dicionário contendo o nome de cada recurso com sua quantidade de reservas.
     * @return mixed Gráfico de pizza do uso dos recursos.
     */
    private function gerarGraficoDeUsoDeRecursos($recursos)
    {

        $cores = array();
        foreach ($recursos as $recurso) $cores[] = $this->gerarCor();

        $grafico = app()->chartjs
            ->name('recursos')
            ->type('doughnut')
            //->element('recursos')
            ->labels(array_keys($recursos))
            ->size(['width' => 400, 'height' => 200])
            ->datasets([
                [
                    'data' => array_values($recursos),
                    'backgroundColor' => $cores,
                ]
            ])
            ->options([
                'animation' => [
                    'animateRotate' => true,
                    'animationEasing' => "easeOutBounce",
                ],
                'responsive' => true,
                'maintainAspectRatio' => true,
            ]);

        return $grafico;
    }

    /**
     * Gera o gráfico de linha sobre a quantidade de reservas por mês
     * @param array $reservas Dicionário contendo o nome do mês como chave e a quantidade de reservas como valor da chave
     * @return mixed Gráfico de linha com a quantidade de reservas por mês
     */
    private function gerarGraficoDeUsoSobreTempo($reservas)
    {
        $grafico = app()->chartjs
            ->name('reservas')
            ->type('line')
            ->labels(array_keys($reservas))
            ->size(['width' => 400, 'height' => 200])
            ->datasets([
                [
                    'label' => 'Quantidade de reservas',
                    'data' => array_values($reservas),
                    'fill' => true,
                    'borderColor' => "#0073b7",
                    'backgroundColor' => "#0073b7",
                    'pointBorderColor' => 'black',
                    'pointBackgroundColor' => 'white',
                ]
            ])
            ->options([
                'responsive' => true,
                'maintainAspectRatio' => true,
            ]);

        return $grafico;
    }
}