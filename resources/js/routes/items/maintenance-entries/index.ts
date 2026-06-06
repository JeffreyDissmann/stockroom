import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\MaintenanceEntryController::store
* @see app/Http/Controllers/Items/MaintenanceEntryController.php:22
* @route '/items/{item}/maintenance-entries'
*/
export const store = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/items/{item}/maintenance-entries',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Items\MaintenanceEntryController::store
* @see app/Http/Controllers/Items/MaintenanceEntryController.php:22
* @route '/items/{item}/maintenance-entries'
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
* @see \App\Http\Controllers\Items\MaintenanceEntryController::store
* @see app/Http/Controllers/Items/MaintenanceEntryController.php:22
* @route '/items/{item}/maintenance-entries'
*/
store.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Items\MaintenanceEntryController::destroy
* @see app/Http/Controllers/Items/MaintenanceEntryController.php:31
* @route '/items/{item}/maintenance-entries/{maintenanceEntry}'
*/
export const destroy = (args: { item: number | { id: number }, maintenanceEntry: number | { id: number } } | [item: number | { id: number }, maintenanceEntry: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/items/{item}/maintenance-entries/{maintenanceEntry}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Items\MaintenanceEntryController::destroy
* @see app/Http/Controllers/Items/MaintenanceEntryController.php:31
* @route '/items/{item}/maintenance-entries/{maintenanceEntry}'
*/
destroy.url = (args: { item: number | { id: number }, maintenanceEntry: number | { id: number } } | [item: number | { id: number }, maintenanceEntry: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            item: args[0],
            maintenanceEntry: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
        maintenanceEntry: typeof args.maintenanceEntry === 'object'
        ? args.maintenanceEntry.id
        : args.maintenanceEntry,
    }

    return destroy.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{maintenanceEntry}', parsedArgs.maintenanceEntry.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\MaintenanceEntryController::destroy
* @see app/Http/Controllers/Items/MaintenanceEntryController.php:31
* @route '/items/{item}/maintenance-entries/{maintenanceEntry}'
*/
destroy.delete = (args: { item: number | { id: number }, maintenanceEntry: number | { id: number } } | [item: number | { id: number }, maintenanceEntry: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const maintenanceEntries = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default maintenanceEntries