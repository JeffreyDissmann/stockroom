import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\ImportController::index
* @see app/Http/Controllers/Household/ImportController.php:20
* @route '/household/import'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/household/import',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Household\ImportController::index
* @see app/Http/Controllers/Household/ImportController.php:20
* @route '/household/import'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\ImportController::index
* @see app/Http/Controllers/Household/ImportController.php:20
* @route '/household/import'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\ImportController::index
* @see app/Http/Controllers/Household/ImportController.php:20
* @route '/household/import'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\ImportController::start
* @see app/Http/Controllers/Household/ImportController.php:27
* @route '/household/import'
*/
export const start = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(options),
    method: 'post',
})

start.definition = {
    methods: ["post"],
    url: '/household/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\ImportController::start
* @see app/Http/Controllers/Household/ImportController.php:27
* @route '/household/import'
*/
start.url = (options?: RouteQueryOptions) => {
    return start.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\ImportController::start
* @see app/Http/Controllers/Household/ImportController.php:27
* @route '/household/import'
*/
start.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(options),
    method: 'post',
})

const ImportController = { index, start }

export default ImportController