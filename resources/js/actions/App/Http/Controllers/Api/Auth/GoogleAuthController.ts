import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::store
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:24
 * @route '/api/v1/auth/google'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/auth/google',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::store
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:24
 * @route '/api/v1/auth/google'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::store
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:24
 * @route '/api/v1/auth/google'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::store
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:24
 * @route '/api/v1/auth/google'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::store
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:24
 * @route '/api/v1/auth/google'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const GoogleAuthController = { store }

export default GoogleAuthController