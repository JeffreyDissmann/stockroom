import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
import maintenanceTasks from './maintenance-tasks'
import homeAssistantLink from './home-assistant-link'
/**
* @see \App\Http\Controllers\Api\V1\ItemController::index
* @see app/Http/Controllers/Api/V1/ItemController.php:31
* @route '/api/v1/items'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/items',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\ItemController::index
* @see app/Http/Controllers/Api/V1/ItemController.php:31
* @route '/api/v1/items'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ItemController::index
* @see app/Http/Controllers/Api/V1/ItemController.php:31
* @route '/api/v1/items'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\ItemController::index
* @see app/Http/Controllers/Api/V1/ItemController.php:31
* @route '/api/v1/items'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\ItemController::show
* @see app/Http/Controllers/Api/V1/ItemController.php:63
* @route '/api/v1/items/{item}'
*/
export const show = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/items/{item}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\ItemController::show
* @see app/Http/Controllers/Api/V1/ItemController.php:63
* @route '/api/v1/items/{item}'
*/
show.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ItemController::show
* @see app/Http/Controllers/Api/V1/ItemController.php:63
* @route '/api/v1/items/{item}'
*/
show.get = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\ItemController::show
* @see app/Http/Controllers/Api/V1/ItemController.php:63
* @route '/api/v1/items/{item}'
*/
show.head = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\ItemController::store
* @see app/Http/Controllers/Api/V1/ItemController.php:73
* @route '/api/v1/items'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/items',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\ItemController::store
* @see app/Http/Controllers/Api/V1/ItemController.php:73
* @route '/api/v1/items'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ItemController::store
* @see app/Http/Controllers/Api/V1/ItemController.php:73
* @route '/api/v1/items'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\V1\ItemController::update
* @see app/Http/Controllers/Api/V1/ItemController.php:90
* @route '/api/v1/items/{item}'
*/
export const update = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/v1/items/{item}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\V1\ItemController::update
* @see app/Http/Controllers/Api/V1/ItemController.php:90
* @route '/api/v1/items/{item}'
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
* @see \App\Http\Controllers\Api\V1\ItemController::update
* @see app/Http/Controllers/Api/V1/ItemController.php:90
* @route '/api/v1/items/{item}'
*/
update.patch = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

const items = {
    index: Object.assign(index, index),
    show: Object.assign(show, show),
    maintenanceTasks: Object.assign(maintenanceTasks, maintenanceTasks),
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    homeAssistantLink: Object.assign(homeAssistantLink, homeAssistantLink),
}

export default items