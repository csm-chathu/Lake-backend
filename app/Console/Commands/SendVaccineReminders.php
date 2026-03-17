<?php

namespace App\Console\Commands;

use App\Models\PatientVaccination;
use App\Services\SmsGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendVaccineReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'vaccinations:send-reminders {--limit=50 : Maximum reminders to send per run} {--dry-run : Output reminders without sending SMS}';

    /**
     * The console command description.
     */
    protected $description = 'Send SMS reminders for upcoming patient vaccinations';

    public function __construct(private SmsGateway $smsGateway)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $vaccinations = PatientVaccination::with(['patient.owner'])
            ->dueForReminder()
            ->orderBy('next_due_at')
            ->limit($limit)
            ->get();

        if ($vaccinations->isEmpty()) {
            $this->info('No vaccination reminders due.');
            return self::SUCCESS;
        }

        foreach ($vaccinations as $vaccination) {
            $patientName = $vaccination->patient?->name ?? 'Unknown patient';
            $ownerName = trim(($vaccination->patient?->owner?->first_name ?? '') . ' ' . ($vaccination->patient?->owner?->last_name ?? ''));
            $dueDateLabel = optional($vaccination->next_due_at)
                ?->timezone(config('app.timezone'))
                ?->format('d M Y');

            if ($dryRun) {
                $this->line(sprintf('[DRY RUN] %s vaccine for %s due on %s (owner: %s)',
                    $vaccination->vaccine_name,
                    $patientName,
                    $dueDateLabel ?? 'soon',
                    $ownerName ?: 'unknown'
                ));
                continue;
            }

            try {
                $sent = $this->smsGateway->sendVaccinationReminder($vaccination);
                if ($sent) {
                    $vaccination->markReminderSent();
                    $this->info(sprintf('Reminder sent for %s (%s)', $patientName, $vaccination->vaccine_name));
                } else {
                    $vaccination->markReminderFailed();
                    $this->warn(sprintf('Reminder failed for %s (%s)', $patientName, $vaccination->vaccine_name));
                }
            } catch (\Throwable $exception) {
                $vaccination->markReminderFailed();
                Log::error('Unable to send vaccination reminder', [
                    'vaccination_id' => $vaccination->id,
                    'message' => $exception->getMessage(),
                ]);
                $this->error(sprintf('Error sending reminder for %s: %s', $patientName, $exception->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
