import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ActivityController::__invoke
* @see app/Http/Controllers/ActivityController.php:16
* @route '/activity'
*/
const ActivityController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ActivityController.url(options),
    method: 'get',
})

ActivityController.definition = {
    methods: ["get","head"],
    url: '/activity',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ActivityController::__invoke
* @see app/Http/Controllers/ActivityController.php:16
* @route '/activity'
*/
ActivityController.url = (options?: RouteQueryOptions) => {
    return ActivityController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ActivityController::__invoke
* @see app/Http/Controllers/ActivityController.php:16
* @route '/activity'
*/
ActivityController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ActivityController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ActivityController::__invoke
* @see app/Http/Controllers/ActivityController.php:16
* @route '/activity'
*/
ActivityController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ActivityController.url(options),
    method: 'head',
})

export default ActivityController