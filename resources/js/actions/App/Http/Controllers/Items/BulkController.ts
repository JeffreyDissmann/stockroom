import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\BulkController::__invoke
* @see app/Http/Controllers/Items/BulkController.php:35
* @route '/items/bulk'
*/
const BulkController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BulkController.url(options),
    method: 'post',
})

BulkController.definition = {
    methods: ["post"],
    url: '/items/bulk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Items\BulkController::__invoke
* @see app/Http/Controllers/Items/BulkController.php:35
* @route '/items/bulk'
*/
BulkController.url = (options?: RouteQueryOptions) => {
    return BulkController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\BulkController::__invoke
* @see app/Http/Controllers/Items/BulkController.php:35
* @route '/items/bulk'
*/
BulkController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BulkController.url(options),
    method: 'post',
})

export default BulkController