import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::index
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:28
* @route '/api/v1/home-assistant-links'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/home-assistant-links',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::index
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:28
* @route '/api/v1/home-assistant-links'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::index
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:28
* @route '/api/v1/home-assistant-links'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\V1\HomeAssistantLinkController::index
* @see app/Http/Controllers/Api/V1/HomeAssistantLinkController.php:28
* @route '/api/v1/home-assistant-links'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const homeAssistantLinks = {
    index: Object.assign(index, index),
}

export default homeAssistantLinks