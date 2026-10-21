<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Http\Requests\{StoreOrderRequest, UpdateOrderRequest, ChangeOrderStatusRequest, IndexOrderRequest, OrderIdentityRequest};
use App\Modules\Orders\Http\Resources\OrderResource;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Shared\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrderController extends Controller
{
    use ApiResponse;
    public function __construct(private readonly OrderService $orders)
    {
    }
    public function index(IndexOrderRequest $request): AnonymousResourceCollection
    {
        return OrderResource::collection($this->orders->list($request->toDto()));
    }
    public function store(StoreOrderRequest $request): JsonResponse
    {
        return $this->resource(new OrderResource($this->orders->create($request->toDto())), 201);
    }
    public function show(OrderIdentityRequest $request): JsonResponse
    {
        return $this->resource(new OrderResource($this->orders->find($request->toDto())));
    }
    public function update(UpdateOrderRequest $request): JsonResponse
    {
        return $this->resource(new OrderResource($this->orders->update($request->toDto())));
    }
    public function changeStatus(ChangeOrderStatusRequest $request): JsonResponse
    {
        return $this->resource(new OrderResource($this->orders->changeStatus($request->toDto())));
    }
    public function destroy(OrderIdentityRequest $request): Response
    {
        $this->orders->delete($request->toDto());
        return response()->noContent();
    }
}
