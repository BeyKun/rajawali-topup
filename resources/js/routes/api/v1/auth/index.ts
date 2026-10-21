import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::login
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:21
 * @route '/api/v1/auth/login'
 */
export const login = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: login.url(options),
    method: 'post',
})

login.definition = {
    methods: ["post"],
    url: '/api/v1/auth/login',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::login
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:21
 * @route '/api/v1/auth/login'
 */
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::login
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:21
 * @route '/api/v1/auth/login'
 */
login.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: login.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::login
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:21
 * @route '/api/v1/auth/login'
 */
    const loginForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: login.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::login
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:21
 * @route '/api/v1/auth/login'
 */
        loginForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: login.url(options),
            method: 'post',
        })
    
    login.form = loginForm
/**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::google
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:18
 * @route '/api/v1/auth/google'
 */
export const google = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: google.url(options),
    method: 'post',
})

google.definition = {
    methods: ["post"],
    url: '/api/v1/auth/google',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::google
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:18
 * @route '/api/v1/auth/google'
 */
google.url = (options?: RouteQueryOptions) => {
    return google.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::google
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:18
 * @route '/api/v1/auth/google'
 */
google.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: google.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::google
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:18
 * @route '/api/v1/auth/google'
 */
    const googleForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: google.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\Auth\GoogleAuthController::google
 * @see app/Http/Controllers/Api/Auth/GoogleAuthController.php:18
 * @route '/api/v1/auth/google'
 */
        googleForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: google.url(options),
            method: 'post',
        })
    
    google.form = googleForm
const auth = {
    login: Object.assign(login, login),
google: Object.assign(google, google),
}

export default auth