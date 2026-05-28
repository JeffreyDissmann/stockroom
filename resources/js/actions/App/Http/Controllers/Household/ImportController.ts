import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\ImportController::start
* @see app/Http/Controllers/Household/ImportController.php:24
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
* @see app/Http/Controllers/Household/ImportController.php:24
* @route '/household/import'
*/
start.url = (options?: RouteQueryOptions) => {
    return start.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\ImportController::start
* @see app/Http/Controllers/Household/ImportController.php:24
* @route '/household/import'
*/
start.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(options),
    method: 'post',
})

const ImportController = { start }

export default ImportController