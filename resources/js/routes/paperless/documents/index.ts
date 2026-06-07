import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::search
* @see app/Http/Controllers/Items/PaperlessLinkController.php:41
* @route '/paperless/documents'
*/
export const search = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

search.definition = {
    methods: ["get","head"],
    url: '/paperless/documents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::search
* @see app/Http/Controllers/Items/PaperlessLinkController.php:41
* @route '/paperless/documents'
*/
search.url = (options?: RouteQueryOptions) => {
    return search.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::search
* @see app/Http/Controllers/Items/PaperlessLinkController.php:41
* @route '/paperless/documents'
*/
search.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::search
* @see app/Http/Controllers/Items/PaperlessLinkController.php:41
* @route '/paperless/documents'
*/
search.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: search.url(options),
    method: 'head',
})

const documents = {
    search: Object.assign(search, search),
}

export default documents