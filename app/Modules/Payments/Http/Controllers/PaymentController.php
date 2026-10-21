<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Http\Requests\{ProcessPaymentRequest, PaymentIndexRequest, PaymentIdentityRequest};
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function store(ProcessPaymentRequest $request): JsonResponse
    {
        return (new PaymentResource($this->payments->process($request->toDto())))->response()->setStatusCode(201);
    }

    public function index(PaymentIndexRequest $request): AnonymousResourceCollection
    {
        return PaymentResource::collection($this->payments->list($request->toDto()));
    }

    public function forOrder(PaymentIndexRequest $request): AnonymousResourceCollection
    {
        return PaymentResource::collection($this->payments->listForOrder($request->toDto()));
    }

    public function show(PaymentIdentityRequest $request): PaymentResource
    {
        return new PaymentResource($this->payments->find($request->toDto()));
    }

}
