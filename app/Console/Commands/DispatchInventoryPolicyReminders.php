<?php

namespace App\Console\Commands;

use App\Models\InventoryPolicy;
use App\Models\InventoryPolicyReminderDelivery;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\InventoryPolicyReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchInventoryPolicyReminders extends Command
{
    protected $signature = 'inventory:dispatch-policy-reminders';

    protected $description = 'Send overdue-return and monthly inventory verification notifications';

    public function handle(): int
    {
        $policy = InventoryPolicy::current();
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $overdueCutoff = $today->subDays($policy->overdue_escalation_days)->toDateString();
        $sent = 0;

        Transaction::query()
            ->with(['item', 'fromUser', 'user'])
            ->where('status', 'assigned')
            ->whereNotNull('from_user_id')
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<=', $overdueCutoff)
            ->chunkById(100, function ($transactions) use (&$sent, $policy): void {
                foreach ($transactions as $transaction) {
                    $issuer = $transaction->fromUser;
                    if (! $issuer || $issuer->status !== 'active') {
                        continue;
                    }

                    $itemName = $transaction->item?->item_name ?? 'An assigned item';
                    $dueDate = $transaction->expected_return_date?->toDateString() ?? 'an unknown date';
                    $recipientName = $transaction->user
                        ? (trim($transaction->user->first_name.' '.$transaction->user->last_name) ?: $transaction->user->username)
                        : ($transaction->manual_recipient_name ?: 'the assigned recipient');

                    if ($this->deliverOnce(
                        $issuer,
                        'overdue_return',
                        'transaction:'.$transaction->id,
                        'Overdue item return',
                        "{$itemName} assigned to {$recipientName} was due on {$dueDate} and is at least {$policy->overdue_escalation_days} days overdue.",
                        '/transactions',
                    )) {
                        $sent++;
                    }
                }
            });

        $interval = max(1, $policy->inventory_verification_interval_months);
        if ((($today->month - 1) % $interval) === 0) {
            $custodianRoleId = Role::query()
                ->whereRaw('LOWER(role_name) = ?', ['property custodian'])
                ->value('role_id');

            if ($custodianRoleId !== null) {
                $custodians = User::query()
                    ->where('role_id', $custodianRoleId)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->get();
                $referenceKey = $today->format('Y-m');

                foreach ($custodians as $custodian) {
                    if ($this->deliverOnce(
                        $custodian,
                        'inventory_verification',
                        $referenceKey,
                        'Inventory verification reminder',
                        'Please review your inventory records and verify that assigned items are accounted for.',
                        '/inventory',
                    )) {
                        $sent++;
                    }
                }
            }
        }

        $this->info("Sent {$sent} inventory policy reminder(s).");

        return self::SUCCESS;
    }

    private function deliverOnce(
        User $recipient,
        string $type,
        string $referenceKey,
        string $title,
        string $message,
        string $url,
    ): bool {
        return DB::transaction(function () use ($recipient, $type, $referenceKey, $title, $message, $url): bool {
            $alreadyDelivered = InventoryPolicyReminderDelivery::query()
                ->where('recipient_id', $recipient->id)
                ->where('reminder_type', $type)
                ->where('reference_key', $referenceKey)
                ->exists();

            if ($alreadyDelivered) {
                return false;
            }

            InventoryPolicyReminderDelivery::query()->create([
                'recipient_id' => $recipient->id,
                'reminder_type' => $type,
                'reference_key' => $referenceKey,
                'delivered_at' => now(),
            ]);

            $recipient->notify(new InventoryPolicyReminder($title, $message, $url));

            return true;
        });
    }
}
