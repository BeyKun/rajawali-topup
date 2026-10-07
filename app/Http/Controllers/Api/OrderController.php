<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateOrderRequest;
use App\Http\Resources\OrderCreatedResource;
use App\Http\Resources\OrderHistoryResource;
use App\Http\Resources\OrderStatusResource;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Customer order endpoints for the mobile application.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * Create a new order (and reserve a voucher) for the authenticated user.
     *
     * @throws OutOfStockException When the product has no sellable stock.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isOutletProfileComplete()) {
            return response()->json([
                'success' => false,
                'message' => 'Lengkapi profil outlet terlebih dahulu sebelum melakukan transaksi.',
            ], 422);
        }

        $order = $this->orderService->createOrder(
            $user,
            (int) $request->validated('product_id'),
            (string) $request->validated('msisdn'),
        );

        $order->loadMissing('product');

        return response()->json([
            'success' => true,
            'data' => (new OrderCreatedResource($order))->resolve($request),
        ], 201);
    }

    /**
     * Return the current status of one of the authenticated user's orders.
     */
    public function show(Request $request, string $orderNo): JsonResponse
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
     * Cancel one of the authenticated user's unpaid orders.
     *
     * @throws ValidationException When the order is not cancelable.
     */
    public function cancel(Request $request, string $orderNo): JsonResponse
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
     * List the authenticated user's orders, newest first.
     */
    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

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
     * Resolve an order by number, scoped to the authenticated user.
     */
    protected function ownedOrder(Request $request, string $orderNo): ?Order
    {
        /** @var User $user */
        $user = $request->user();

        return Order::query()
            ->with(['product:id,name'])
            ->where('order_no', $orderNo)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Standard not-found response (also hides other users' orders).
     */
    protected function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Pesanan tidak ditemukan.',
        ], 404);
    }
}
