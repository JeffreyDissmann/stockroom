import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\MaintenanceController::__invoke
* @see app/Http/Controllers/MaintenanceController.php:30
* @route '/maintenance'
*/
const MaintenanceController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MaintenanceController.url(options),
    method: 'get',
})

MaintenanceController.definition = {
    methods: ["get","head"],
    url: '/maintenance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MaintenanceController::__invoke
* @see app/Http/Controllers/MaintenanceController.php:30
* @route '/maintenance'
*/
MaintenanceController.url = (options?: RouteQueryOptions) => {
    return MaintenanceController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MaintenanceController::__invoke
* @see app/Http/Controllers/MaintenanceController.php:30
* @route '/maintenance'
*/
MaintenanceController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MaintenanceController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MaintenanceController::__invoke
* @see app/Http/Controllers/MaintenanceController.php:30
* @route '/maintenance'
*/
MaintenanceController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: MaintenanceController.url(options),
    method: 'head',
})

export default MaintenanceController