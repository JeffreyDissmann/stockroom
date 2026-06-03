import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
import items from './items'
import rooms from './rooms'
import tags from './tags'
/**
* @see \App\Http\Controllers\Api\V1\UserController::__invoke
* @see app/Http/Controllers/Api/V1/UserController.php:17
* @route '/api/v1/user'
*/
export const user = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: user.url(options),
    method: 'get',
})

user.definition = {
    methods: ["get","head"],
    url: '/api/v1/user',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\UserController::__invoke
* @see app/Http/Controllers/Api/V1/UserController.php:17
* @route '/api/v1/user'
*/
user.url = (options?: RouteQueryOptions) => {
    return user.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\UserController::__invoke
* @see app/Http/Controllers/Api/V1/UserController.php:17
* @route '/api/v1/user'
*/
user.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: user.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\UserController::__invoke
* @see app/Http/Controllers/Api/V1/UserController.php:17
* @route '/api/v1/user'
*/
user.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: user.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\StatisticsController::__invoke
* @see app/Http/Controllers/Api/V1/StatisticsController.php:23
* @route '/api/v1/statistics'
*/
export const statistics = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: statistics.url(options),
    method: 'get',
})

statistics.definition = {
    methods: ["get","head"],
    url: '/api/v1/statistics',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\StatisticsController::__invoke
* @see app/Http/Controllers/Api/V1/StatisticsController.php:23
* @route '/api/v1/statistics'
*/
statistics.url = (options?: RouteQueryOptions) => {
    return statistics.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\StatisticsController::__invoke
* @see app/Http/Controllers/Api/V1/StatisticsController.php:23
* @route '/api/v1/statistics'
*/
statistics.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: statistics.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\StatisticsController::__invoke
* @see app/Http/Controllers/Api/V1/StatisticsController.php:23
* @route '/api/v1/statistics'
*/
statistics.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: statistics.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\V1\SearchController::__invoke
* @see app/Http/Controllers/Api/V1/SearchController.php:22
* @route '/api/v1/search'
*/
export const search = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

search.definition = {
    methods: ["get","head"],
    url: '/api/v1/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\SearchController::__invoke
* @see app/Http/Controllers/Api/V1/SearchController.php:22
* @route '/api/v1/search'
*/
search.url = (options?: RouteQueryOptions) => {
    return search.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\SearchController::__invoke
* @see app/Http/Controllers/Api/V1/SearchController.php:22
* @route '/api/v1/search'
*/
search.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\SearchController::__invoke
* @see app/Http/Controllers/Api/V1/SearchController.php:22
* @route '/api/v1/search'
*/
search.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: search.url(options),
    method: 'head',
})

const v1 = {
    user: Object.assign(user, user),
    statistics: Object.assign(statistics, statistics),
    items: Object.assign(items, items),
    rooms: Object.assign(rooms, rooms),
    tags: Object.assign(tags, tags),
    search: Object.assign(search, search),
}

export default v1