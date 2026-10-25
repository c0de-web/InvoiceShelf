<?php

namespace App\Http\Controllers\V1\Admin\Engagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\EngagementRequest;
use App\Http\Resources\EngagementResource;
use App\Models\Engagement;
use App\Models\RecurringInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EngagementsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Engagement::class);

        $engagements = Engagement::whereCompany()
            ->applyFilters($request->all())
            ->with(['customer.currency', 'milestones.invoice', 'recurringInvoices'])
            ->latest()
            ->paginate($request->integer('limit', 10));

        return EngagementResource::collection($engagements);
    }

    public function store(EngagementRequest $request): JsonResponse
    {
        $this->authorize('create', Engagement::class);

        $engagement = DB::transaction(function () use ($request) {
            $engagement = Engagement::create([...$request->getEngagementPayload(), 'creator_id' => $request->user()->id]);
            $this->syncSchedule($engagement, $request);

            return $engagement;
        });

        return (new EngagementResource($this->loaded($engagement)))->response()->setStatusCode(201);
    }

    public function show(Engagement $engagement): EngagementResource
    {
        $this->authorize('view', $engagement);

        return new EngagementResource($this->loaded($engagement));
    }

    public function update(EngagementRequest $request, Engagement $engagement): EngagementResource
    {
        $this->authorize('update', $engagement);

        DB::transaction(function () use ($request, $engagement) {
            $engagement->update($request->getEngagementPayload());
            $this->syncSchedule($engagement, $request);
        });

        return new EngagementResource($this->loaded($engagement));
    }

    /**
     * Refused once it has invoices; cancel it instead, so its history stays.
     */
    public function destroy(Engagement $engagement): JsonResponse
    {
        $this->authorize('delete', $engagement);

        if ($engagement->invoicesQuery()->exists()) {
            return response()->json([
                'message' => 'This engagement has invoices. Set it to Cancelled instead of deleting it.',
            ], 422);
        }

        $engagement->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Milestones as sent (invoiced and settled ones were validated as unchanged), and the
     * recurring invoices linked to it.
     */
    private function syncSchedule(Engagement $engagement, EngagementRequest $request): void
    {
        $milestones = $engagement->isProject() ? $request->milestonesWithAmounts() : [];
        $keep = array_filter(array_column($milestones, 'id'));

        $engagement->milestones()->whereNotIn('id', $keep)->delete();

        foreach ($milestones as $milestone) {
            $values = [
                'position' => $milestone['position'],
                'name' => $milestone['name'],
                'amount' => $milestone['amount'],
                'percent' => $milestone['percent'],
                'due_date' => $milestone['due_date'] ?? null,
                'settled_at' => $milestone['settled_at'] ?? null,
                'settled_reference' => $milestone['settled_reference'] ?? null,
            ];

            $milestone['id']
                ? $engagement->milestones()->whereKey($milestone['id'])->update($values)
                : $engagement->milestones()->create($values);
        }

        $linked = $engagement->isProject() ? [] : (array) $request->input('recurring_invoice_ids', []);
        RecurringInvoice::where('engagement_id', $engagement->id)->whereNotIn('id', $linked)->update(['engagement_id' => null]);
        RecurringInvoice::whereIn('id', $linked)->update(['engagement_id' => $engagement->id]);
    }

    private function loaded(Engagement $engagement): Engagement
    {
        return $engagement->fresh(['customer.currency', 'milestones.invoice', 'recurringInvoices']);
    }
}
