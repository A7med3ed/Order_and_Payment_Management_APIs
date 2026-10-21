<?php

declare(strict_types=1);

namespace App\Modules\Orders\Policies;

use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Auth\Authenticatable;

final class OrderPolicy
{
    public function view(Authenticatable $user, Order $order): bool
    {
        return $order->isOwnedBy((int) $user->getAuthIdentifier());
    }
    public function update(Authenticatable $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
    public function delete(Authenticatable $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
