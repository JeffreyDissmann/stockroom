import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\TagController::__invoke
* @see app/Http/Controllers/Api/V1/TagController.php:14
* @route '/api/v1/tags'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/tags',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\TagController::__invoke
* @see app/Http/Controllers/Api/V1/TagController.php:14
* @route '/api/v1/tags'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\TagController::__invoke
* @see app/Http/Controllers/Api/V1/TagController.php:14
* @route '/api/v1/tags'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\TagController::__invoke
* @see app/Http/Controllers/Api/V1/TagController.php:14
* @route '/api/v1/tags'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const tags = {
    index: Object.assign(index, index),
}

export default tags