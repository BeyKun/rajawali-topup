import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/qris'
 */
export const qris = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: qris.url(options),
    method: 'post',
})

qris.definition = {
    methods: ["post"],
    url: '/api/v1/webhooks/qris',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/qris'
 */
qris.url = (options?: RouteQueryOptions) => {
    return qris.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/qris'
 */
qris.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: qris.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/qris'
 */
    const qrisForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: qris.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/qris'
 */
        qrisForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: qris.url(options),
            method: 'post',
        })
    
    qris.form = qrisForm
/**
* @see \App\Http\Controllers\Api\WebhookController::midtrans
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/midtrans'
 */
export const midtrans = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: midtrans.url(options),
    method: 'post',
})

midtrans.definition = {
    methods: ["post"],
    url: '/api/v1/webhooks/midtrans',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WebhookController::midtrans
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/midtrans'
 */
midtrans.url = (options?: RouteQueryOptions) => {
    return midtrans.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WebhookController::midtrans
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/midtrans'
 */
midtrans.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: midtrans.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\WebhookController::midtrans
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/midtrans'
 */
    const midtransForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: midtrans.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\WebhookController::midtrans
 * @see app/Http/Controllers/Api/WebhookController.php:30
 * @route '/api/v1/webhooks/midtrans'
 */
        midtransForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: midtrans.url(options),
            method: 'post',
        })
    
    midtrans.form = midtransForm
const webhooks = {
    qris: Object.assign(qris, qris),
midtrans: Object.assign(midtrans, midtrans),
}

export default webhooks