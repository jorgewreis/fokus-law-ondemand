<?php

namespace App\Dashboard;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Synthetic dashboard for the public landing. Every figure is illustrative.
 * Parties are "Réu A." and case numbers are "Processo 0000xxx-xx".
 * Nothing here is a record from the 1ª Vara.
 */
class ExampleUnitBoard implements UnitBoard
{
    public function present(DateTimeInterface $moment): array
    {
        $today = Carbon::parse($moment)->timezone('America/Bahia');

        return [
            'example' => true,
            'source' => 'example',
            'date_label' => $this->dateLabel($today),
            'clock_label' => $today->format('H').'h'.$today->format('i'),
            'month_label' => $this->monthLabel($today),
            'weekdays' => ['S', 'T', 'Q', 'Q', 'S', 'S', 'D'],
            'calendar' => $this->calendar($today),
            'kpis' => [
                ['label' => 'Sentenças no mês', 'value' => '42', 'suffix' => '/ 50', 'detail' => 'Exemplo · 84% da meta', 'bar' => 84, 'tone' => 'sage'],
                ['label' => 'Despachos no mês', 'value' => '128', 'suffix' => null, 'detail' => 'Exemplo · acumulado', 'bar' => 70, 'tone' => 'purple'],
                ['label' => 'Audiências hoje', 'value' => '4', 'suffix' => '/ 7', 'detail' => 'Realizadas / pauta', 'bar' => 57, 'tone' => 'sage'],
                ['label' => 'Meta CNJ', 'value' => '112%', 'suffix' => null, 'detail' => 'Cumprimento (exemplo)', 'bar' => 100, 'tone' => 'gold'],
                ['label' => 'Conclusos / fila', 'value' => '9', 'suffix' => '/ 15', 'detail' => 'Pendentes (exemplo)', 'bar' => 60, 'tone' => 'sage-light'],
            ],
            'hearings' => [
                ['time' => '09:00', 'type' => 'Instrução', 'room' => 'Sala 01', 'process' => 'Processo 0000xxx-xx', 'party' => 'Réu A.', 'status' => 'Em andamento', 'tone' => 'go', 'current' => true],
                ['time' => '10:30', 'type' => 'Admonitório', 'room' => 'Sala 02', 'process' => 'Processo 0000xxx-xx', 'party' => 'Réu B.', 'status' => 'Aguardando', 'tone' => 'wait', 'current' => false],
                ['time' => '13:00', 'type' => 'Sessão do Júri', 'room' => 'Plenário', 'process' => 'Processo 0000xxx-xx', 'party' => 'Réu C.', 'status' => 'Agendada', 'tone' => 'plan', 'current' => false],
                ['time' => '14:30', 'type' => 'Justificação', 'room' => 'Sala 01', 'process' => 'Processo 0000xxx-xx', 'party' => 'Réu D.', 'status' => 'Agendada', 'tone' => 'plan', 'current' => false],
                ['time' => '16:00', 'type' => 'Videoconferência', 'room' => 'Sala Virtual', 'process' => 'Processo 0000xxx-xx', 'party' => 'Réu E.', 'status' => 'Confirmada', 'tone' => 'ok', 'current' => false],
            ],
            'events' => [
                ['time' => '08:30', 'label' => 'Abertura do expediente'],
                ['time' => '09:00', 'label' => 'Pauta de audiências — manhã'],
                ['time' => '13:30', 'label' => 'Reunião de gabinete (exemplo)'],
                ['time' => '14:00', 'label' => 'Pauta de audiências — tarde'],
                ['time' => '18:00', 'label' => 'Início do plantão (exemplo)'],
            ],
            'notices' => [
                ['title' => 'Ambiente novo', 'body' => 'Ambiente isolado em /novologin, separado do modelo atual. Dados ilustrativos.'],
                ['title' => 'Pauta da tarde', 'body' => 'Três audiências remanescentes a partir das 13h (exemplo).'],
                ['title' => 'Manutenção (exemplo)', 'body' => 'Sábado, 28/11, das 08h às 10h.'],
            ],
            'duty' => [
                'title' => 'Plantão judiciário',
                'body' => $today->format('d/m').', 18h, até '.$today->copy()->addDay()->format('d/m').', 08h (exemplo). Escala completa após o acesso.',
            ],
            'unit' => [
                'name' => '1ª Vara Crime de Ilhéus',
                'court' => 'Tribunal de Justiça da Bahia',
                'district' => 'Ilhéus-BA',
                'address_label' => 'Endereço (exemplo)',
                'address' => 'Av. Exemplo, s/n — Centro',
                'phone_label' => 'Telefone (exemplo)',
                'phone' => '(00) 0000-0000',
                'hours_label' => 'Atendimento (exemplo)',
                'hours' => '09h–18h, dias úteis',
            ],
        ];
    }

    private function dateLabel(Carbon $today): string
    {
        $weekdays = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
        $months = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

        return ucfirst($weekdays[$today->dayOfWeek]).', '.$today->day.' de '.$months[$today->month].' de '.$today->year;
    }

    private function monthLabel(Carbon $today): string
    {
        $short = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        return $short[$today->month].' '.$today->year;
    }

    /**
     * @return list<array{day: int|null, today: bool}>
     */
    private function calendar(Carbon $today): array
    {
        $start = $today->copy()->startOfMonth();
        $cells = [];

        for ($blank = 0; $blank < $start->dayOfWeekIso - 1; $blank++) {
            $cells[] = ['day' => null, 'today' => false];
        }

        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $cells[] = ['day' => $day, 'today' => $day === $today->day];
        }

        return $cells;
    }
}
