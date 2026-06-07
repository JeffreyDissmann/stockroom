import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::index
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:28
* @route '/api/v1/items/{item}/maintenance-tasks'
*/
export const index = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/items/{item}/maintenance-tasks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::index
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:28
* @route '/api/v1/items/{item}/maintenance-tasks'
*/
index.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::index
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:28
* @route '/api/v1/items/{item}/maintenance-tasks'
*/
index.get = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::index
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:28
* @route '/api/v1/items/{item}/maintenance-tasks'
*/
index.head = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::store
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:37
* @route '/api/v1/items/{item}/maintenance-tasks'
*/
export const store = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/items/{item}/maintenance-tasks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::store
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:37
* @route '/api/v1/items/{item}/maintenance-tasks'
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
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::store
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:37
* @route '/api/v1/items/{item}/maintenance-tasks'
*/
store.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::complete
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:54
* @route '/api/v1/maintenance-tasks/{maintenanceTask}/complete'
*/
export const complete = (args: { maintenanceTask: number | { id: number } } | [maintenanceTask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/api/v1/maintenance-tasks/{maintenanceTask}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::complete
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:54
* @route '/api/v1/maintenance-tasks/{maintenanceTask}/complete'
*/
complete.url = (args: { maintenanceTask: number | { id: number } } | [maintenanceTask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { maintenanceTask: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { maintenanceTask: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            maintenanceTask: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        maintenanceTask: typeof args.maintenanceTask === 'object'
        ? args.maintenanceTask.id
        : args.maintenanceTask,
    }

    return complete.definition.url
            .replace('{maintenanceTask}', parsedArgs.maintenanceTask.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::complete
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:54
* @route '/api/v1/maintenance-tasks/{maintenanceTask}/complete'
*/
complete.post = (args: { maintenanceTask: number | { id: number } } | [maintenanceTask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

const MaintenanceTaskController = { index, store, complete }

export default MaintenanceTaskController