import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\StatisticsController::__invoke
* @see app/Http/Controllers/Api/V1/StatisticsController.php:24
* @route '/api/v1/statistics'
*/
const StatisticsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: StatisticsController.url(options),
    method: 'get',
})

StatisticsController.definition = {
    methods: ["get","head"],
    url: '/api/v1/statistics',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\StatisticsController::__invoke
* @see app/Http/Controllers/Api/V1/StatisticsController.php:24
* @route '/api/v1/statistics'
*/
StatisticsController.url = (options?: RouteQueryOptions) => {
    return StatisticsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\StatisticsController::__invoke
* @see app/Http/Controllers/Api/V1/StatisticsController.php:24
* @route '/api/v1/statistics'
*/
StatisticsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: StatisticsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\StatisticsController::__invoke
* @see app/Http/Controllers/Api/V1/StatisticsController.php:24
* @route '/api/v1/statistics'
*/
StatisticsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: StatisticsController.url(options),
    method: 'head',
})

export default StatisticsController