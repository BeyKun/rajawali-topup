export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

export type VoucherStatus =
    | 'AVAILABLE'
    | 'RESERVED'
    | 'REDEEMED'
    | 'EXPIRED'
    | 'FAILED';

export type AdminVoucher = {
    id: number;
    serial_number: string;
    product_name: string | null;
    sell_price: number | null;
    hpp_price?: number | null;
    margin_percentage?: number | null;
    city_ids?: number[];
    status: VoucherStatus;
    region: string | null;
    expired_date: string | null;
    redeemed_msisdn: string | null;
    created_at: string | null;
};

export type VoucherCheckResult = {
    name: string;
    description: string;
    validity: string;
    expired_date: string;
    region: string;
    status_code: number;
    is_valid: boolean;
    status_message: string;
};

export type PaymentStatus =
    | 'UNPAID'
    | 'PAID'
    | 'EXPIRED'
    | 'FAILED'
    | 'CANCELED'
    | 'REFUNDED'
    | 'REFUND_PENDING';
export type RedeemStatus = 'PENDING' | 'PROCESSING' | 'SUCCESS' | 'FAILED' | 'CANCELED';

export type AdminProductRow = {
    id: number;
    name: string;
    quota_description: string;
    validity_days: number;
    region: string | null;
    sell_price: number;
    hpp_price: number;
    is_active: boolean;
    sort_order: number;
    available_stock: number;
    reserved_stock: number;
    total_stock: number;
};

export type AdminProductSummary = {
    total_products: number;
    active_products: number;
    available_stock: number;
};

export type AdminOrderRow = {
    id: number;
    order_no: string;
    user_name: string | null;
    product_name: string | null;
    msisdn: string;
    total_amount: number;
    payment_status: PaymentStatus;
    redeem_status: RedeemStatus;
    retry_count: number;
    created_at: string | null;
};

export type AdminOrderDetail = {
    id: number;
    order_no: string;
    user: {
        name: string | null;
        email: string | null;
    };
    product_name: string | null;
    msisdn: string;
    amount: number;
    admin_fee: number;
    total_amount: number;
    payment_channel: string;
    payment_status: PaymentStatus;
    redeem_status: RedeemStatus;
    retry_count: number;
    qris_string: string | null;
    qris_url: string | null;
    qris_expired_at: string | null;
    payment_ref_id: string | null;
    paid_at: string | null;
    redeem_response_code: string | null;
    redeem_response_raw: Record<string, unknown> | null;
    voucher_serial_number: string | null;
    voucher_status: string | null;
    refund_amount: number | null;
    refund_ref_id: string | null;
    refund_reason: string | null;
    refunded_at: string | null;
    created_at: string | null;
};

export type AdminApiLog = {
    id: number;
    endpoint: string;
    response_code: number;
    duration_ms: number;
    created_at: string | null;
    request_payload: Record<string, unknown> | null;
    response_payload: Record<string, unknown> | null;
};

export type AdminDashboardStats = {
    total_orders: number;
    orders_today: number;
    revenue_today: number;
    revenue_this_month: number;
    available_vouchers: number;
    redeemed_vouchers: number;
    failed_orders: number;
};

export type AdminSalesPoint = {
    date: string;
    revenue: number;
    orders: number;
};

export type AdminLowStockItem = {
    id: number;
    name: string;
    available_stock: number;
};

export type AdminRecentOrder = {
    order_no: string;
    product_name: string | null;
    msisdn: string;
    total_amount: number;
    payment_status: PaymentStatus;
    redeem_status: RedeemStatus;
    created_at: string | null;
};

export type AdminAreaRow = {
    id: number;
    region: string;
    city_id: number;
    city_name: string | null;
    province_id: number | null;
    province_name: string | null;
};

export type AdminKabupatenAdminRow = {
    id: number;
    name: string;
    email: string;
    province_id: number | null;
    province_name: string | null;
    city_id: number | null;
    city_name: string | null;
    created_at: string | null;
};

export type AdminOutletRow = {
    id: number;
    name: string;
    phone: string | null;
    outlet_name: string | null;
    whatsapp: string | null;
    province_id: number | null;
    province_name: string | null;
    city_id: number | null;
    city_name: string | null;
    district_id: number | null;
    village_id: number | null;
    address: string | null;
    profile_completed: boolean;
    created_at: string | null;
};

export type DiscountType = 'fixed' | 'percentage';

export type AdminPromoBanner = {
    id: number;
    title: string;
    image_path: string;
    image_url: string;
    target_url: string | null;
    is_active: boolean;
    is_featured: boolean;
    sort_order: number;
    created_at: string | null;
};

export type AdminPromoVoucher = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    discount_type: DiscountType;
    discount_value: number;
    max_discount_amount: number | null;
    min_spend: number;
    usage_limit: number | null;
    used_count: number;
    start_date: string;
    end_date: string;
    is_active: boolean;
    is_usable: boolean;
    created_at: string | null;
};
