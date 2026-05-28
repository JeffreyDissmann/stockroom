import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ItemPhotoAnalysisController::__invoke
* @see app/Http/Controllers/ItemPhotoAnalysisController.php:41
* @route '/items/analyze-photo'
*/
const ItemPhotoAnalysisController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ItemPhotoAnalysisController.url(options),
    method: 'post',
})

ItemPhotoAnalysisController.definition = {
    methods: ["post"],
    url: '/items/analyze-photo',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ItemPhotoAnalysisController::__invoke
* @see app/Http/Controllers/ItemPhotoAnalysisController.php:41
* @route '/items/analyze-photo'
*/
ItemPhotoAnalysisController.url = (options?: RouteQueryOptions) => {
    return ItemPhotoAnalysisController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemPhotoAnalysisController::__invoke
* @see app/Http/Controllers/ItemPhotoAnalysisController.php:41
* @route '/items/analyze-photo'
*/
ItemPhotoAnalysisController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ItemPhotoAnalysisController.url(options),
    method: 'post',
})

export default ItemPhotoAnalysisController