import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/qris'
 */
const qris3d980f8e9c18ecd027c46b716501cc9d = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: qris3d980f8e9c18ecd027c46b716501cc9d.url(options),
    method: 'post',
})

qris3d980f8e9c18ecd027c46b716501cc9d.definition = {
    methods: ["post"],
    url: '/api/v1/webhooks/qris',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/qris'
 */
qris3d980f8e9c18ecd027c46b716501cc9d.url = (options?: RouteQueryOptions) => {
    return qris3d980f8e9c18ecd027c46b716501cc9d.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/qris'
 */
qris3d980f8e9c18ecd027c46b716501cc9d.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: qris3d980f8e9c18ecd027c46b716501cc9d.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/qris'
 */
    const qris3d980f8e9c18ecd027c46b716501cc9dForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: qris3d980f8e9c18ecd027c46b716501cc9d.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/qris'
 */
        qris3d980f8e9c18ecd027c46b716501cc9dForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: qris3d980f8e9c18ecd027c46b716501cc9d.url(options),
            method: 'post',
        })
    
    qris3d980f8e9c18ecd027c46b716501cc9d.form = qris3d980f8e9c18ecd027c46b716501cc9dForm
    /**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/midtrans'
 */
const qrisfcff4e49518f64e507a135c3c8b2123c = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: qrisfcff4e49518f64e507a135c3c8b2123c.url(options),
    method: 'post',
})

qrisfcff4e49518f64e507a135c3c8b2123c.definition = {
    methods: ["post"],
    url: '/api/v1/webhooks/midtrans',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/midtrans'
 */
qrisfcff4e49518f64e507a135c3c8b2123c.url = (options?: RouteQueryOptions) => {
    return qrisfcff4e49518f64e507a135c3c8b2123c.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/midtrans'
 */
qrisfcff4e49518f64e507a135c3c8b2123c.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: qrisfcff4e49518f64e507a135c3c8b2123c.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/midtrans'
 */
    const qrisfcff4e49518f64e507a135c3c8b2123cForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: qrisfcff4e49518f64e507a135c3c8b2123c.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\WebhookController::qris
 * @see app/Http/Controllers/Api/WebhookController.php:28
 * @route '/api/v1/webhooks/midtrans'
 */
        qrisfcff4e49518f64e507a135c3c8b2123cForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: qrisfcff4e49518f64e507a135c3c8b2123c.url(options),
            method: 'post',
        })
    
    qrisfcff4e49518f64e507a135c3c8b2123c.form = qrisfcff4e49518f64e507a135c3c8b2123cForm

/**
* Multiple routes resolve to \App\Http\Controllers\Api\WebhookController::qris, so this export is a
* dictionary keyed by URI rather than a callable. Call a specific route with `qris['<uri>'](...)`,
* or import the route by name from your generated `routes/` directory.
*/
export const qris = {
    '/api/v1/webhooks/qris': qris3d980f8e9c18ecd027c46b716501cc9d,
    '/api/v1/webhooks/midtrans': qrisfcff4e49518f64e507a135c3c8b2123c,
}

const WebhookController = { qris }

export default WebhookController