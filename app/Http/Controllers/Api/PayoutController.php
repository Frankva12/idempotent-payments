<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayoutRequest;
use App\Http\Resources\PayoutResource;
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

        return PayoutResource::make($payout)->response()->setStatusCode(201);
    }

    public function show(Payout $payout): PayoutResource
    {
        return PayoutResource::make($payout);
    }
}
