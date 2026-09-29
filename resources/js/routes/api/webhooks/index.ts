import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
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
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/qris'
 */
qris.url = (options?: RouteQueryOptions) => {
    return qris.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/qris'
 */
qris.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: qris.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/qris'
 */
    const qrisForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: qris.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/qris'
 */
        qrisForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: qris.url(options),
            method: 'post',
        })
    
    qris.form = qrisForm
const webhooks = {
    qris: Object.assign(qris, qris),
}

export default webhooks