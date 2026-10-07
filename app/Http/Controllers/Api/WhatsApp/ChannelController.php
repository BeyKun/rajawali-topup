<?php

namespace App\Http\Controllers\Api\WhatsApp;

use App\Enums\OrderChannel;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WhatsApp\ResolveCustomerRequest;
use App\Http\Requests\Api\WhatsApp\StoreChannelOrderRequest;
use App\Http\Resources\OrderCreatedResource;
use App\Http\Resources\OrderHistoryResource;
use App\Http\Resources\OrderStatusResource;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Channel\ChannelCustomerService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Internal Channel API consumed by the WhatsApp bot service.
 *
 * These endpoints reuse the same order, catalog and payment services as the
 * mobile app, so the WhatsApp channel is just another front-end onto the same
 * business logic. Access is gated by a static service token by the
 * `channel.token` middleware instead of a customer token.
 */
class ChannelController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly ChannelCustomerService $customers,
    ) {}

    /**
     * Resolve (or create) the customer behind a WhatsApp identifier.
     */
    public function resolveCustomer(ResolveCustomerRequest $request): JsonResponse
    {
        $result = $this->customers->resolveCustomer(
            (string) $request->validated('wa_id'),
            $request->validated('display_name'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $result['user']->id,
                'name' => $result['user']->name,
                'phone' => $result['user']->phone,
                'is_new' => $result['is_new'],
            ],
        ]);
    }

    /**
     * Catalog of active products that still have available stock.
     */
    public function products(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('category:id,name')
            ->withCount([
                'vouchers as stock_count' => fn ($query) => $query->where('status', 'AVAILABLE'),
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $product): bool => (int) $product->stock_count > 0)
            ->values();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products)->resolve($request),
        ]);
    }

    /**
     * Create a channel order and generate its QRIS invoice.
     *
     * @throws OutOfStockException When the product has no sellable stock.
     */
    public function storeOrder(StoreChannelOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->resolveUser((string) $request->validated('wa_id'));

        $order = $this->orderService->createOrder(
            $user,
            (int) $request->validated('product_id'),
            (string) $request->validated('msisdn'),
        );

        $order->forceFill([
            'channel' => OrderChannel::WhatsApp,
            'channel_ref' => (string) $request->validated('wa_id'),
        ])->save();

        $order->loadMissing('product');

        return response()->json([
            'success' => true,
            'data' => (new OrderCreatedResource($order))->resolve($request),
        ], 201);
    }

    /**
     * Return the status of one of the channel customer's orders.
     */
    public function showOrder(Request $request, string $orderNo): JsonResponse
    {
        $order = $this->ownedOrder($request, $orderNo);

        if ($order === null) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data' => (new OrderStatusResource($order))->resolve($request),
        ]);
    }

    /**
     * List the channel customer's orders, newest first.
     */
    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->resolveUser((string) $request->query('wa_id'));

        $orders = Order::query()
            ->with('product:id,name')
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => OrderHistoryResource::collection($orders)->resolve($request),
        ]);
    }

    /**
     * Cancel one of the channel customer's unpaid orders.
     */
    public function cancelOrder(Request $request, string $orderNo): JsonResponse
    {
        $order = $this->ownedOrder($request, $orderNo);

        if ($order === null) {
            return $this->notFound();
        }

        try {
            $order = $this->orderService->cancelOrder($order);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->validator->errors()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => (new OrderStatusResource($order))->resolve($request),
        ]);
    }

    /**
     * Resolve the channel customer by WhatsApp number.
     */
    protected function resolveUser(string $waId): User
    {
        return $this->customers->resolveCustomer($waId)['user'];
    }

    /**
     * Resolve an order by number, scoped to the channel customer.
     */
    protected function ownedOrder(Request $request, string $orderNo): ?Order
    {
        $waId = (string) ($request->query('wa_id') ?? $request->input('wa_id'));

        /** @var User $user */
        $user = $this->resolveUser($waId);

        return Order::query()
            ->with('product:id,name')
            ->where('order_no', $orderNo)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Standard not-found response (also hides other customers' orders).
     */
    protected function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Pesanan tidak ditemukan.',
        ], 404);
    }
}
