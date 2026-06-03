import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\UserController::__invoke
* @see app/Http/Controllers/Api/V1/UserController.php:17
* @route '/api/v1/user'
*/
const UserController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: UserController.url(options),
    method: 'get',
})

UserController.definition = {
    methods: ["get","head"],
    url: '/api/v1/user',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\UserController::__invoke
* @see app/Http/Controllers/Api/V1/UserController.php:17
* @route '/api/v1/user'
*/
UserController.url = (options?: RouteQueryOptions) => {
    return UserController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\UserController::__invoke
* @see app/Http/Controllers/Api/V1/UserController.php:17
* @route '/api/v1/user'
*/
UserController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: UserController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\UserController::__invoke
* @see app/Http/Controllers/Api/V1/UserController.php:17
* @route '/api/v1/user'
*/
UserController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: UserController.url(options),
    method: 'head',
})

export default UserController