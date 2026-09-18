<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Alert;
use App\Models\Incident;
use App\Models\Item;
use App\Models\User;
use App\Notifications\AlertRaised;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class AlertRaiser
{
    /**
     * Raise an alert for a Cambodia-related item at or above the alert severity, once per item.
     */
    public function evaluate(Item $item): ?Alert
    {
        if (! $item->is_cambodia || $item->severity < Item::ALERT_SEVERITY || $item->alert()->exists()) {
            return null;
        }

        $alert = $item->alert()->create([
            'severity' => $item->severity,
            'reason' => $this->reason($item),
        ]);

        $this->attachToOpenIncident($item);
        $this->notify($alert);

        return $alert;
    }

    private function reason(Item $item): string
    {
        return match (true) {
            $item->kind === 'threat' => 'Threat intelligence mentions a Cambodian target',
            $item->kind === 'vulnerability' => 'Vulnerability affects a monitored product',
            $item->watch_hits !== null && $item->watch_hits !== [] => 'Watchlist match: '.implode(', ', array_slice($item->watch_hits, 0, 3)),
            default => 'High-severity Cambodia-related report',
        };
    }

    /**
     * Group the item with an open incident that shares a CVE, domain or threat actor.
     */
    private function attachToOpenIncident(Item $item): void
    {
        $keys = self::correlationKeys($item);

        if ($keys === []) {
            return;
        }

        $incident = Incident::where('status', '!=', 'closed')
            ->with('items:id,entities,watch_hits')
            ->latest()
            ->limit(50)
            ->get()
            ->first(fn (Incident $incident) => $incident->items->contains(
                fn (Item $other) => array_intersect($keys, self::correlationKeys($other)) !== []
            ));

        $incident?->items()->syncWithoutDetaching([$item->id]);
    }

    /**
     * @return list<string>
     */
    public static function correlationKeys(Item $item): array
    {
        $entities = $item->entities ?? [];
        $keys = array_merge(
            $entities['cves'] ?? [],
            $entities['domains'] ?? [],
            $entities['threat_actors'] ?? [],
        );

        return array_values(array_unique(array_map('mb_strtolower', array_filter($keys, 'is_string'))));
    }

    private function notify(Alert $alert): void
    {
        if (config('cyber.alerts.mail')) {
            $recipients = User::whereIn('role', [Role::Admin, Role::Analyst])->get();
            Notification::send($recipients, new AlertRaised($alert));
        }

        $chatId = config('cyber.alerts.telegram_chat_id');
        $token = config('services.telegram.bot_token');

        if ($chatId && $token) {
            try {
                Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => sprintf("⚠️ Severity %d: %s\n%s\n%s", $alert->severity, $alert->item->displayTitle(), $alert->reason, route('alerts.index')),
                    'disable_web_page_preview' => true,
                ])->throw();
            } catch (Throwable $e) {
                Log::warning('Telegram alert failed', ['alert_id' => $alert->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
