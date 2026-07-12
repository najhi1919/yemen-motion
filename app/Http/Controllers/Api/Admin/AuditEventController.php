<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAuditEventsRequest;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;

class AuditEventController extends Controller
{
    public function index(ListAuditEventsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        // نطبق فلاتر allowlist فقط، دون بحث حر داخل metadata أو بيانات المستخدمين.
        $events = AuditEvent::query()
            ->when(
                filled($validated['event_type'] ?? null),
                fn ($query) => $query->where('event_type', $validated['event_type']),
            )
            ->when(
                filled($validated['category'] ?? null),
                fn ($query) => $query->where('category', $validated['category']),
            )
            ->when(
                filled($validated['severity'] ?? null),
                fn ($query) => $query->where('severity', $validated['severity']),
            )
            ->when(
                filled($validated['outcome'] ?? null),
                fn ($query) => $query->where('outcome', $validated['outcome']),
            )
            ->when(
                isset($validated['actor_id']),
                fn ($query) => $query->where('actor_id', $validated['actor_id']),
            )
            ->when(
                filled($validated['target_type'] ?? null),
                fn ($query) => $query->where('target_type', $validated['target_type']),
            )
            ->when(
                isset($validated['target_id']),
                fn ($query) => $query->where('target_id', $validated['target_id']),
            )
            ->when(
                filled($validated['from'] ?? null),
                fn ($query) => $query->whereDate('occurred_at', '>=', $validated['from']),
            )
            ->when(
                filled($validated['to'] ?? null),
                fn ($query) => $query->whereDate('occurred_at', '<=', $validated['to']),
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditEvent $event): array => $this->eventPayload($event));

        return response()->json([
            'success' => true,
            'data' => $events,
            'message' => 'تم جلب سجلات التدقيق بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * يعيد الحقول الآمنة المعتمدة فقط دون علاقات أو model كامل.
     *
     * @return array<string, mixed>
     */
    private function eventPayload(AuditEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'category' => $event->category,
            'severity' => $event->severity,
            'actor_type' => $event->actor_type,
            'actor_id' => $event->actor_id,
            'actor_role' => $event->actor_role,
            'target_type' => $event->target_type,
            'target_id' => $event->target_id,
            'action' => $event->action,
            'outcome' => $event->outcome,
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'request_id' => $event->request_id,
            'correlation_id' => $event->correlation_id,
            'metadata' => $event->metadata,
            'occurred_at' => $event->occurred_at?->toJSON(),
            'created_at' => $event->created_at?->toJSON(),
        ];
    }
}
