import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../wayfinder'
/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/'
*/
export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/'
*/
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/'
*/
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/'
*/
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/'
*/
home.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: home.url(options),
    method: 'post',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/'
*/
home.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: home.url(options),
    method: 'put',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/'
*/
home.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: home.url(options),
    method: 'patch',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/'
*/
home.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: home.url(options),
    method: 'delete',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/'
*/
home.options = (options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: home.url(options),
    method: 'options',
})

/**
* @see \App\Http\Controllers\DashboardController::__invoke
* @see app/Http/Controllers/DashboardController.php:22
* @route '/dashboard'
*/
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::__invoke
* @see app/Http/Controllers/DashboardController.php:22
* @route '/dashboard'
*/
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::__invoke
* @see app/Http/Controllers/DashboardController.php:22
* @route '/dashboard'
*/
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::__invoke
* @see app/Http/Controllers/DashboardController.php:22
* @route '/dashboard'
*/
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SearchController::__invoke
* @see app/Http/Controllers/SearchController.php:20
* @route '/search'
*/
export const search = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

search.definition = {
    methods: ["get","head"],
    url: '/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SearchController::__invoke
* @see app/Http/Controllers/SearchController.php:20
* @route '/search'
*/
search.url = (options?: RouteQueryOptions) => {
    return search.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SearchController::__invoke
* @see app/Http/Controllers/SearchController.php:20
* @route '/search'
*/
search.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SearchController::__invoke
* @see app/Http/Controllers/SearchController.php:20
* @route '/search'
*/
search.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: search.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ActivityController::__invoke
* @see app/Http/Controllers/ActivityController.php:16
* @route '/activity'
*/
export const activity = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: activity.url(options),
    method: 'get',
})

activity.definition = {
    methods: ["get","head"],
    url: '/activity',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ActivityController::__invoke
* @see app/Http/Controllers/ActivityController.php:16
* @route '/activity'
*/
activity.url = (options?: RouteQueryOptions) => {
    return activity.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ActivityController::__invoke
* @see app/Http/Controllers/ActivityController.php:16
* @route '/activity'
*/
activity.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: activity.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ActivityController::__invoke
* @see app/Http/Controllers/ActivityController.php:16
* @route '/activity'
*/
activity.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: activity.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MaintenanceController::__invoke
* @see app/Http/Controllers/MaintenanceController.php:29
* @route '/maintenance'
*/
export const maintenance = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: maintenance.url(options),
    method: 'get',
})

maintenance.definition = {
    methods: ["get","head"],
    url: '/maintenance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MaintenanceController::__invoke
* @see app/Http/Controllers/MaintenanceController.php:29
* @route '/maintenance'
*/
maintenance.url = (options?: RouteQueryOptions) => {
    return maintenance.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MaintenanceController::__invoke
* @see app/Http/Controllers/MaintenanceController.php:29
* @route '/maintenance'
*/
maintenance.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: maintenance.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MaintenanceController::__invoke
* @see app/Http/Controllers/MaintenanceController.php:29
* @route '/maintenance'
*/
maintenance.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: maintenance.url(options),
    method: 'head',
})

/**
* @see routes/settings.php:22
* @route '/settings/appearance'
*/
export const appearance = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: appearance.url(options),
    method: 'get',
})

appearance.definition = {
    methods: ["get","head"],
    url: '/settings/appearance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/settings.php:22
* @route '/settings/appearance'
*/
appearance.url = (options?: RouteQueryOptions) => {
    return appearance.definition.url + queryParams(options)
}

/**
* @see routes/settings.php:22
* @route '/settings/appearance'
*/
appearance.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: appearance.url(options),
    method: 'get',
})

/**
* @see routes/settings.php:22
* @route '/settings/appearance'
*/
appearance.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: appearance.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:21
* @route '/login'
*/
export const login = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

login.definition = {
    methods: ["get","head"],
    url: '/login',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:21
* @route '/login'
*/
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:21
* @route '/login'
*/
login.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:21
* @route '/login'
*/
login.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: login.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:25
* @route '/register/{token}'
*/
export const register = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: register.url(args, options),
    method: 'get',
})

register.definition = {
    methods: ["get","head"],
    url: '/register/{token}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:25
* @route '/register/{token}'
*/
register.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    if (Array.isArray(args)) {
        args = {
            token: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        token: args.token,
    }

    return register.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:25
* @route '/register/{token}'
*/
register.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: register.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:25
* @route '/register/{token}'
*/
register.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: register.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:44
* @route '/logout'
*/
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:44
* @route '/logout'
*/
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:44
* @route '/logout'
*/
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

