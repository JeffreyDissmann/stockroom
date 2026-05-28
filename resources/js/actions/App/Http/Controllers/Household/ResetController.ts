import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\ResetController::wipe
* @see app/Http/Controllers/Household/ResetController.php:23
* @route '/household/reset'
*/
export const wipe = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: wipe.url(options),
    method: 'post',
})

wipe.definition = {
    methods: ["post"],
    url: '/household/reset',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\ResetController::wipe
* @see app/Http/Controllers/Household/ResetController.php:23
* @route '/household/reset'
*/
wipe.url = (options?: RouteQueryOptions) => {
    return wipe.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\ResetController::wipe
* @see app/Http/Controllers/Household/ResetController.php:23
* @route '/household/reset'
*/
wipe.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: wipe.url(options),
    method: 'post',
})

const ResetController = { wipe }

export default ResetController