<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\FollowUpSchedule;
use App\Models\ProposalSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateFollowUpsCommand extends Command
{
    protected $signature = 'follow-ups:generate
                            {--dry-run : Show what would be created without actually creating}';

    protected $description = 'Generate automated follow-ups based on configured schedules';

    public function handle(): int
    {
        $schedules = FollowUpSchedule::where('is_active', true)->get();

        if ($schedules->isEmpty()) {
            $this->info('No active follow-up schedules configured.');
            return Command::SUCCESS;
        }

        $created = 0;

        foreach ($schedules as $schedule) {
            $created += $this->processSchedule($schedule);
        }

        $this->markOverdue();
        $this->info("Generated {$created} follow-up(s). Marked overdue items.");

        return Command::SUCCESS;
    }

    private function processSchedule(FollowUpSchedule $schedule): int
    {
        $count = 0;
        $triggerDate = now()->subDays($schedule->delay_days);

        if ($schedule->trigger_event === 'proposal_submitted') {
            $proposals = ProposalSubmission::where('organization_id', $schedule->organization_id)
                ->where('status', 'submitted')
                ->whereDate('updated_at', $triggerDate->toDateString())
                ->get();

            foreach ($proposals as $proposal) {
                $exists = FollowUp::where('proposal_submission_id', $proposal->id)
                    ->where('type', $schedule->follow_up_type)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $assignedTo = $schedule->assign_to_owner
                    ? $proposal->owner_id
                    : $schedule->assign_to_user_id;

                if (!$this->option('dry-run')) {
                    FollowUp::create([
                        'organization_id' => $schedule->organization_id,
                        'type' => $schedule->follow_up_type,
                        'subject' => $this->renderTemplate((string) $schedule->subject_template, $proposal),
                        'message' => $this->renderTemplate($schedule->message_template ?? '', $proposal),
                        'scheduled_date' => now()->addDays($schedule->delay_days),
                        'assigned_to' => $assignedTo ?? $proposal->owner_id ?? $proposal->created_by,
                        'proposal_submission_id' => $proposal->id,
                        'status' => 'scheduled',
                        'is_automated' => true,
                        // created_by is NOT NULL; attribute the automated row to
                        // the proposal's owner (falling back to its creator).
                        'created_by' => $proposal->owner_id ?? $proposal->created_by,
                    ]);
                }

                $count++;
            }
        }

        return $count;
    }

    private function renderTemplate(string $template, ProposalSubmission $proposal): string
    {
        return str_replace(
            ['{proposal_number}', '{project_name}', '{agency_name}'],
            [$proposal->proposal_number, $proposal->project_name, $proposal->agency_name ?? ''],
            $template
        );
    }

    private function markOverdue(): void
    {
        FollowUp::where('status', 'scheduled')
            ->where('scheduled_date', '<', now())
            ->update(['status' => 'overdue']);
    }
}
