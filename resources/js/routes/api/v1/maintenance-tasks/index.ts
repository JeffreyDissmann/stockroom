import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\MaintenanceTaskController::complete
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:59
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
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:59
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
* @see app/Http/Controllers/Api/V1/MaintenanceTaskController.php:59
* @route '/api/v1/maintenance-tasks/{maintenanceTask}/complete'
*/
complete.post = (args: { maintenanceTask: number | { id: number } } | [maintenanceTask: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

const maintenanceTasks = {
    complete: Object.assign(complete, complete),
}

export default maintenanceTasks