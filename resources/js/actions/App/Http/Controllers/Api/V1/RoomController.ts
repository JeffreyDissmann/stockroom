import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\RoomController::__invoke
* @see app/Http/Controllers/Api/V1/RoomController.php:20
* @route '/api/v1/rooms'
*/
const RoomController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RoomController.url(options),
    method: 'get',
})

RoomController.definition = {
    methods: ["get","head"],
    url: '/api/v1/rooms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\RoomController::__invoke
* @see app/Http/Controllers/Api/V1/RoomController.php:20
* @route '/api/v1/rooms'
*/
RoomController.url = (options?: RouteQueryOptions) => {
    return RoomController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\RoomController::__invoke
* @see app/Http/Controllers/Api/V1/RoomController.php:20
* @route '/api/v1/rooms'
*/
RoomController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RoomController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\RoomController::__invoke
* @see app/Http/Controllers/Api/V1/RoomController.php:20
* @route '/api/v1/rooms'
*/
RoomController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: RoomController.url(options),
    method: 'head',
})

export default RoomController