import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\ApiTokenController::index
* @see app/Http/Controllers/Settings/ApiTokenController.php:23
* @route '/settings/api-tokens'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/api-tokens',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\ApiTokenController::index
* @see app/Http/Controllers/Settings/ApiTokenController.php:23
* @route '/settings/api-tokens'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ApiTokenController::index
* @see app/Http/Controllers/Settings/ApiTokenController.php:23
* @route '/settings/api-tokens'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\ApiTokenController::index
* @see app/Http/Controllers/Settings/ApiTokenController.php:23
* @route '/settings/api-tokens'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\ApiTokenController::store
* @see app/Http/Controllers/Settings/ApiTokenController.php:42
* @route '/settings/api-tokens'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/api-tokens',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\ApiTokenController::store
* @see app/Http/Controllers/Settings/ApiTokenController.php:42
* @route '/settings/api-tokens'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ApiTokenController::store
* @see app/Http/Controllers/Settings/ApiTokenController.php:42
* @route '/settings/api-tokens'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\ApiTokenController::destroy
* @see app/Http/Controllers/Settings/ApiTokenController.php:56
* @route '/settings/api-tokens/{token}'
*/
export const destroy = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/api-tokens/{token}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\ApiTokenController::destroy
* @see app/Http/Controllers/Settings/ApiTokenController.php:56
* @route '/settings/api-tokens/{token}'
*/
destroy.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ApiTokenController::destroy
* @see app/Http/Controllers/Settings/ApiTokenController.php:56
* @route '/settings/api-tokens/{token}'
*/
destroy.delete = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const apiTokens = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default apiTokens