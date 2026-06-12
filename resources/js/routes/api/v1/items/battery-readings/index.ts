import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\BatteryController::store
* @see app/Http/Controllers/Api/V1/BatteryController.php:35
* @route '/api/v1/items/{item}/battery-readings'
*/
export const store = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/items/{item}/battery-readings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::store
* @see app/Http/Controllers/Api/V1/BatteryController.php:35
* @route '/api/v1/items/{item}/battery-readings'
*/
store.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\BatteryController::store
* @see app/Http/Controllers/Api/V1/BatteryController.php:35
* @route '/api/v1/items/{item}/battery-readings'
*/
store.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const batteryReadings = {
    store: Object.assign(store, store),
}

export default batteryReadings