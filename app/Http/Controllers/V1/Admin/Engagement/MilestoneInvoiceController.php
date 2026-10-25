<?php

namespace App\Http\Controllers\V1\Admin\Engagement;

use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\EngagementMilestone;
use App\Services\MilestoneInvoicer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MilestoneInvoiceController extends Controller
{
    /**
     * Create the draft invoice for one milestone of a project.
     */
    public function __invoke(Request $request, Engagement $engagement, EngagementMilestone $milestone, MilestoneInvoicer $invoicer): JsonResponse
    {
        $this->authorize('invoice', $engagement);
        abort_unless($milestone->engagement_id === $engagement->id, 404);

        try {
            $invoice = $invoicer->invoice($milestone, $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number]], 201);
    }
}
