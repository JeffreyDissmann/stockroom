import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\BatteryController::show
* @see app/Http/Controllers/Api/V1/BatteryController.php:27
* @route '/api/v1/items/{item}/battery'
*/
export const show = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/items/{item}/battery',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::show
* @see app/Http/Controllers/Api/V1/BatteryController.php:27
* @route '/api/v1/items/{item}/battery'
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
* @see \App\Http\Controllers\Api\V1\BatteryController::show
* @see app/Http/Controllers/Api/V1/BatteryController.php:27
* @route '/api/v1/items/{item}/battery'
*/
show.get = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::show
* @see app/Http/Controllers/Api/V1/BatteryController.php:27
* @route '/api/v1/items/{item}/battery'
*/
show.head = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::storeReading
* @see app/Http/Controllers/Api/V1/BatteryController.php:35
* @route '/api/v1/items/{item}/battery-readings'
*/
export const storeReading = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeReading.url(args, options),
    method: 'post',
})

storeReading.definition = {
    methods: ["post"],
    url: '/api/v1/items/{item}/battery-readings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::storeReading
* @see app/Http/Controllers/Api/V1/BatteryController.php:35
* @route '/api/v1/items/{item}/battery-readings'
*/
storeReading.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return storeReading.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::storeReading
* @see app/Http/Controllers/Api/V1/BatteryController.php:35
* @route '/api/v1/items/{item}/battery-readings'
*/
storeReading.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeReading.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::storeChange
* @see app/Http/Controllers/Api/V1/BatteryController.php:49
* @route '/api/v1/items/{item}/battery-changes'
*/
export const storeChange = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeChange.url(args, options),
    method: 'post',
})

storeChange.definition = {
    methods: ["post"],
    url: '/api/v1/items/{item}/battery-changes',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::storeChange
* @see app/Http/Controllers/Api/V1/BatteryController.php:49
* @route '/api/v1/items/{item}/battery-changes'
*/
storeChange.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return storeChange.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::storeChange
* @see app/Http/Controllers/Api/V1/BatteryController.php:49
* @route '/api/v1/items/{item}/battery-changes'
*/
storeChange.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeChange.url(args, options),
    method: 'post',
})

const BatteryController = { show, storeReading, storeChange }

export default BatteryController