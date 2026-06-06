import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::store
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:29
* @route '/items/{item}/maintenance-tasks'
*/
export const store = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/items/{item}/maintenance-tasks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::store
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:29
* @route '/items/{item}/maintenance-tasks'
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
* @see \App\Http\Controllers\Items\MaintenanceTaskController::store
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:29
* @route '/items/{item}/maintenance-tasks'
*/
store.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::update
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:44
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}'
*/
export const update = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/items/{item}/maintenance-tasks/{maintenanceTask}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::update
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:44
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}'
*/
update.url = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            item: args[0],
            maintenanceTask: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
        maintenanceTask: typeof args.maintenanceTask === 'object'
        ? args.maintenanceTask.id
        : args.maintenanceTask,
    }

    return update.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{maintenanceTask}', parsedArgs.maintenanceTask.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::update
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:44
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}'
*/
update.patch = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::destroy
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:60
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}'
*/
export const destroy = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/items/{item}/maintenance-tasks/{maintenanceTask}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::destroy
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:60
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}'
*/
destroy.url = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            item: args[0],
            maintenanceTask: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
        maintenanceTask: typeof args.maintenanceTask === 'object'
        ? args.maintenanceTask.id
        : args.maintenanceTask,
    }

    return destroy.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{maintenanceTask}', parsedArgs.maintenanceTask.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::destroy
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:60
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}'
*/
destroy.delete = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::complete
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:75
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}/complete'
*/
export const complete = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/items/{item}/maintenance-tasks/{maintenanceTask}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::complete
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:75
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}/complete'
*/
complete.url = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            item: args[0],
            maintenanceTask: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
        maintenanceTask: typeof args.maintenanceTask === 'object'
        ? args.maintenanceTask.id
        : args.maintenanceTask,
    }

    return complete.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{maintenanceTask}', parsedArgs.maintenanceTask.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::complete
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:75
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}/complete'
*/
complete.post = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::skip
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:110
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}/skip'
*/
export const skip = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: skip.url(args, options),
    method: 'post',
})

skip.definition = {
    methods: ["post"],
    url: '/items/{item}/maintenance-tasks/{maintenanceTask}/skip',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::skip
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:110
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}/skip'
*/
skip.url = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            item: args[0],
            maintenanceTask: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
        maintenanceTask: typeof args.maintenanceTask === 'object'
        ? args.maintenanceTask.id
        : args.maintenanceTask,
    }

    return skip.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{maintenanceTask}', parsedArgs.maintenanceTask.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\MaintenanceTaskController::skip
* @see app/Http/Controllers/Items/MaintenanceTaskController.php:110
* @route '/items/{item}/maintenance-tasks/{maintenanceTask}/skip'
*/
skip.post = (args: { item: number | { id: number }, maintenanceTask: number | { id: number } } | [item: number | { id: number }, maintenanceTask: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: skip.url(args, options),
    method: 'post',
})

const maintenanceTasks = {
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    complete: Object.assign(complete, complete),
    skip: Object.assign(skip, skip),
}

export default maintenanceTasks