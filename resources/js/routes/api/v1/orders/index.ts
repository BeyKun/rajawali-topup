import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\OrderController::store
 * @see app/Http/Controllers/Api/OrderController.php:30
 * @route '/api/v1/orders'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/orders',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\OrderController::store
 * @see app/Http/Controllers/Api/OrderController.php:30
 * @route '/api/v1/orders'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\OrderController::store
 * @see app/Http/Controllers/Api/OrderController.php:30
 * @route '/api/v1/orders'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\OrderController::store
 * @see app/Http/Controllers/Api/OrderController.php:30
 * @route '/api/v1/orders'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\OrderController::store
 * @see app/Http/Controllers/Api/OrderController.php:30
 * @route '/api/v1/orders'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Api\OrderController::history
 * @see app/Http/Controllers/Api/OrderController.php:104
 * @route '/api/v1/orders/history'
 */
export const history = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: history.url(options),
    method: 'get',
})

history.definition = {
    methods: ["get","head"],
    url: '/api/v1/orders/history',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\OrderController::history
 * @see app/Http/Controllers/Api/OrderController.php:104
 * @route '/api/v1/orders/history'
 */
history.url = (options?: RouteQueryOptions) => {
    return history.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\OrderController::history
 * @see app/Http/Controllers/Api/OrderController.php:104
 * @route '/api/v1/orders/history'
 */
history.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: history.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\OrderController::history
 * @see app/Http/Controllers/Api/OrderController.php:104
 * @route '/api/v1/orders/history'
 */
history.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: history.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\OrderController::history
 * @see app/Http/Controllers/Api/OrderController.php:104
 * @route '/api/v1/orders/history'
 */
    const historyForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: history.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\OrderController::history
 * @see app/Http/Controllers/Api/OrderController.php:104
 * @route '/api/v1/orders/history'
 */
        historyForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: history.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\OrderController::history
 * @see app/Http/Controllers/Api/OrderController.php:104
 * @route '/api/v1/orders/history'
 */
        historyForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: history.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    history.form = historyForm
/**
* @see \App\Http\Controllers\Api\OrderController::show
 * @see app/Http/Controllers/Api/OrderController.php:59
 * @route '/api/v1/orders/{orderNo}'
 */
export const show = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/orders/{orderNo}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\OrderController::show
 * @see app/Http/Controllers/Api/OrderController.php:59
 * @route '/api/v1/orders/{orderNo}'
 */
show.url = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { orderNo: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    orderNo: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        orderNo: args.orderNo,
                }

    return show.definition.url
            .replace('{orderNo}', parsedArgs.orderNo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\OrderController::show
 * @see app/Http/Controllers/Api/OrderController.php:59
 * @route '/api/v1/orders/{orderNo}'
 */
show.get = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\OrderController::show
 * @see app/Http/Controllers/Api/OrderController.php:59
 * @route '/api/v1/orders/{orderNo}'
 */
show.head = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\OrderController::show
 * @see app/Http/Controllers/Api/OrderController.php:59
 * @route '/api/v1/orders/{orderNo}'
 */
    const showForm = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\OrderController::show
 * @see app/Http/Controllers/Api/OrderController.php:59
 * @route '/api/v1/orders/{orderNo}'
 */
        showForm.get = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\OrderController::show
 * @see app/Http/Controllers/Api/OrderController.php:59
 * @route '/api/v1/orders/{orderNo}'
 */
        showForm.head = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\Api\OrderController::cancel
 * @see app/Http/Controllers/Api/OrderController.php:78
 * @route '/api/v1/orders/{orderNo}/cancel'
 */
export const cancel = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/api/v1/orders/{orderNo}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\OrderController::cancel
 * @see app/Http/Controllers/Api/OrderController.php:78
 * @route '/api/v1/orders/{orderNo}/cancel'
 */
cancel.url = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { orderNo: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    orderNo: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        orderNo: args.orderNo,
                }

    return cancel.definition.url
            .replace('{orderNo}', parsedArgs.orderNo.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\OrderController::cancel
 * @see app/Http/Controllers/Api/OrderController.php:78
 * @route '/api/v1/orders/{orderNo}/cancel'
 */
cancel.post = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\OrderController::cancel
 * @see app/Http/Controllers/Api/OrderController.php:78
 * @route '/api/v1/orders/{orderNo}/cancel'
 */
    const cancelForm = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\OrderController::cancel
 * @see app/Http/Controllers/Api/OrderController.php:78
 * @route '/api/v1/orders/{orderNo}/cancel'
 */
        cancelForm.post = (args: { orderNo: string | number } | [orderNo: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, options),
            method: 'post',
        })
    
    cancel.form = cancelForm
const orders = {
    store: Object.assign(store, store),
history: Object.assign(history, history),
show: Object.assign(show, show),
cancel: Object.assign(cancel, cancel),
}

export default orders