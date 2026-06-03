import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\TagController::__invoke
* @see app/Http/Controllers/Api/V1/TagController.php:14
* @route '/api/v1/tags'
*/
const TagController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TagController.url(options),
    method: 'get',
})

TagController.definition = {
    methods: ["get","head"],
    url: '/api/v1/tags',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\TagController::__invoke
* @see app/Http/Controllers/Api/V1/TagController.php:14
* @route '/api/v1/tags'
*/
TagController.url = (options?: RouteQueryOptions) => {
    return TagController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\TagController::__invoke
* @see app/Http/Controllers/Api/V1/TagController.php:14
* @route '/api/v1/tags'
*/
TagController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TagController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\TagController::__invoke
* @see app/Http/Controllers/Api/V1/TagController.php:14
* @route '/api/v1/tags'
*/
TagController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: TagController.url(options),
    method: 'head',
})

export default TagController