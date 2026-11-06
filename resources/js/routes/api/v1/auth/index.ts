import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::login
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:23
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
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:23
 * @route '/api/v1/auth/login'
 */
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::login
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:23
 * @route '/api/v1/auth/login'
 */
login.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: login.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::login
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:23
 * @route '/api/v1/auth/login'
 */
    const loginForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: login.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::login
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:23
 * @route '/api/v1/auth/login'
 */
        loginForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: login.url(options),
            method: 'post',
        })
    
    login.form = loginForm
/**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::resetPassword
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:58
 * @route '/api/v1/auth/reset-password'
 */
export const resetPassword = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resetPassword.url(options),
    method: 'post',
})

resetPassword.definition = {
    methods: ["post"],
    url: '/api/v1/auth/reset-password',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::resetPassword
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:58
 * @route '/api/v1/auth/reset-password'
 */
resetPassword.url = (options?: RouteQueryOptions) => {
    return resetPassword.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::resetPassword
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:58
 * @route '/api/v1/auth/reset-password'
 */
resetPassword.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resetPassword.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::resetPassword
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:58
 * @route '/api/v1/auth/reset-password'
 */
    const resetPasswordForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resetPassword.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\Auth\PhoneAuthController::resetPassword
 * @see app/Http/Controllers/Api/Auth/PhoneAuthController.php:58
 * @route '/api/v1/auth/reset-password'
 */
        resetPasswordForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resetPassword.url(options),
            method: 'post',
        })
    
    resetPassword.form = resetPasswordForm
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
resetPassword: Object.assign(resetPassword, resetPassword),
google: Object.assign(google, google),
}

export default auth