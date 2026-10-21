<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Contracts\OrderReaderInterface;
use App\Modules\Ledger\Contracts\LedgerReaderInterface;
use App\Modules\Ledger\Http\Requests\LedgerIndexRequest;
use App\Modules\Ledger\Http\Resources\LedgerEntryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LedgerController extends Controller
{
    public function __construct(private readonly OrderReaderInterface $orders, private readonly LedgerReaderInterface $ledger, private readonly \App\Modules\Payments\Contracts\PaymentReaderInterface $payments)
    {
    }
    public function index(LedgerIndexRequest $request): AnonymousResourceCollection
    {
        $data = $request->toDto();
        $this->orders->findOwned($data->orderId, $data->userId);
        return LedgerEntryResource::collection($this->ledger->forOrder($data));
    }

    public function forPayment(\App\Modules\Ledger\Http\Requests\PaymentLedgerRequest $request): AnonymousResourceCollection
    {
        $data = $request->toDto();
        $this->payments->findOwned($data->paymentId, $data->userId);
        return LedgerEntryResource::collection($this->ledger->forPayment($data->paymentId, $data->userId, $data->pagination));
    }
}
