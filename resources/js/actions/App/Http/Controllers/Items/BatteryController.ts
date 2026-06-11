import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\BatteryController::change
* @see app/Http/Controllers/Items/BatteryController.php:26
* @route '/items/{item}/battery-changes'
*/
export const change = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: change.url(args, options),
    method: 'post',
})

change.definition = {
    methods: ["post"],
    url: '/items/{item}/battery-changes',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Items\BatteryController::change
* @see app/Http/Controllers/Items/BatteryController.php:26
* @route '/items/{item}/battery-changes'
*/
change.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return change.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\BatteryController::change
* @see app/Http/Controllers/Items/BatteryController.php:26
* @route '/items/{item}/battery-changes'
*/
change.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: change.url(args, options),
    method: 'post',
})

const BatteryController = { change }

export default BatteryController