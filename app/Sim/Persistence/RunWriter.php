<?php

declare(strict_types=1);

namespace App\Sim\Persistence;

use App\Models\MatchEvent as MatchEventModel;
use App\Models\SimulationRun;
use App\Sim\Experiment\ArmSummary;
use App\Sim\Experiment\RunReport;
use App\Sim\Experiment\SampledMatch;
use Illuminate\Support\Facades\DB;

final class RunWriter
{
    public function write(RunReport $report): SimulationRun
    {
        return DB::transaction(function () use ($report): SimulationRun {
            $run = SimulationRun::create([
                'seed' => $report->runSeed,
                'matches' => $report->matches,
                'low_vision' => $report->low->vision,
                'high_vision' => $report->high->vision,
                'separated' => $report->separated(),
                'report' => $this->reportPayload($report),
            ]);

            foreach ($report->samples as $sample) {
                $this->writeSample($run, $sample);
            }

            return $run;
        });
    }

    private function writeSample(SimulationRun $run, SampledMatch $sample): void
    {
        $result = $sample->result;

        $matchResult = $run->matchResults()->create([
            'arm' => $sample->arm,
            'seed' => $sample->seed,
            'home_score' => $result->goals,
            'away_score' => 0,
            'shots' => $result->shots,
            'chances' => $result->shots,
            'passes_completed' => $result->passesCompleted,
            'progressive_passes' => $result->progressivePasses,
        ]);

        $now = now();
        $rows = [];

        foreach ($result->events as $event) {
            $data = $event->toArray();

            $rows[] = [
                'simulation_run_id' => $run->id,
                'match_result_id' => $matchResult->id,
                'minute' => $data['minute'],
                'type' => $data['type'],
                'actor_id' => $data['actor_id'],
                'target_id' => $data['target_id'],
                'success' => $data['success'],
                'decision' => $data['decision'] !== null ? json_encode($data['decision']) : null,
                'roll' => $data['roll'] !== null ? json_encode($data['roll']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            MatchEventModel::insert($rows);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(RunReport $report): array
    {
        return [
            'config' => $report->config(),
            'separated' => $report->separated(),
            'deltas' => [
                'gap_improvement' => $report->gapImprovement(),
                'progressive_lift' => $report->progressiveLift(),
                'chances_lift' => $report->chancesLift(),
            ],
            'arms' => [
                $this->armPayload($report->low),
                $this->armPayload($report->high),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function armPayload(ArmSummary $arm): array
    {
        return [
            'label' => $arm->label,
            'vision' => $arm->vision,
            'matches' => $arm->matches,
            'mean_decision_gap' => $arm->meanDecisionGap,
            'progressive_pass_share' => $arm->progressivePassShare,
            'chances_per_90' => $arm->chancesPer90,
            'goals_per_90' => $arm->goalsPer90,
        ];
    }
}
