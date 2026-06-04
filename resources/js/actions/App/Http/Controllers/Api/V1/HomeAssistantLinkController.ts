import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::index
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:28
* @route '/api/v1/home-assistant-links'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/home-assistant-links',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::index
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:28
* @route '/api/v1/home-assistant-links'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::index
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:28
* @route '/api/v1/home-assistant-links'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::index
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:28
* @route '/api/v1/home-assistant-links'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::update
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:49
* @route '/api/v1/items/{item}/home-assistant-link'
*/
export const update = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/api/v1/items/{item}/home-assistant-link',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::update
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:49
* @route '/api/v1/items/{item}/home-assistant-link'
*/
update.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { item: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { item: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            item: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
    }

    return update.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::update
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:49
* @route '/api/v1/items/{item}/home-assistant-link'
*/
update.put = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::destroy
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:54
* @route '/api/v1/items/{item}/home-assistant-link'
*/
export const destroy = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/items/{item}/home-assistant-link',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::destroy
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:54
* @route '/api/v1/items/{item}/home-assistant-link'
*/
destroy.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { item: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { item: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            item: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
    }

    return destroy.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::destroy
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:54
* @route '/api/v1/items/{item}/home-assistant-link'
*/
destroy.delete = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const HomeAssistantLinkController = { index, update, destroy }

export default HomeAssistantLinkController