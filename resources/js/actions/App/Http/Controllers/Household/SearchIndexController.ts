import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\SearchIndexController::index
* @see app/Http/Controllers/Household/SearchIndexController.php:17
* @route '/household/search-index'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/household/search-index',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Household\SearchIndexController::index
* @see app/Http/Controllers/Household/SearchIndexController.php:17
* @route '/household/search-index'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\SearchIndexController::index
* @see app/Http/Controllers/Household/SearchIndexController.php:17
* @route '/household/search-index'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\SearchIndexController::index
* @see app/Http/Controllers/Household/SearchIndexController.php:17
* @route '/household/search-index'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\SearchIndexController::rebuild
* @see app/Http/Controllers/Household/SearchIndexController.php:26
* @route '/household/search-index'
*/
export const rebuild = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rebuild.url(options),
    method: 'post',
})

rebuild.definition = {
    methods: ["post"],
    url: '/household/search-index',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\SearchIndexController::rebuild
* @see app/Http/Controllers/Household/SearchIndexController.php:26
* @route '/household/search-index'
*/
rebuild.url = (options?: RouteQueryOptions) => {
    return rebuild.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\SearchIndexController::rebuild
* @see app/Http/Controllers/Household/SearchIndexController.php:26
* @route '/household/search-index'
*/
rebuild.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rebuild.url(options),
    method: 'post',
})

const SearchIndexController = { index, rebuild }

export default SearchIndexController