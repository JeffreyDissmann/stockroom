import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\RoomController::__invoke
* @see app/Http/Controllers/Api/V1/RoomController.php:20
* @route '/api/v1/rooms'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/rooms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\RoomController::__invoke
* @see app/Http/Controllers/Api/V1/RoomController.php:20
* @route '/api/v1/rooms'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\RoomController::__invoke
* @see app/Http/Controllers/Api/V1/RoomController.php:20
* @route '/api/v1/rooms'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\RoomController::__invoke
* @see app/Http/Controllers/Api/V1/RoomController.php:20
* @route '/api/v1/rooms'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const rooms = {
    index: Object.assign(index, index),
}

export default rooms