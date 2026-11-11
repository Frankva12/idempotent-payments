<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayoutRequest;
use App\Http\Resources\PayoutResource;
use App\Jobs\ProcessPayout;
use App\Models\Payout;
use Illuminate\Http\JsonResponse;

class PayoutController extends Controller
{
    public function store(StorePayoutRequest $request): JsonResponse
    {
        $payout = Payout::create([
            ...$request->validated(),
            'status' => PayoutStatus::Pending,
        ]);

        ProcessPayout::dispatch($payout->id);

        // 202: the payout is accepted, and the client polls or listens for the final status.
        return PayoutResource::make($payout)->response()->setStatusCode(202);
    }

    public function show(Payout $payout): PayoutResource
    {
        return PayoutResource::make($payout);
    }
}
