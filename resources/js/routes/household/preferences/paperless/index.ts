import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\PreferencesController::relinkAll
* @see app/Http/Controllers/Household/PreferencesController.php:78
* @route '/household/preferences/paperless/relink-all'
*/
export const relinkAll = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: relinkAll.url(options),
    method: 'post',
})

relinkAll.definition = {
    methods: ["post"],
    url: '/household/preferences/paperless/relink-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\PreferencesController::relinkAll
* @see app/Http/Controllers/Household/PreferencesController.php:78
* @route '/household/preferences/paperless/relink-all'
*/
relinkAll.url = (options?: RouteQueryOptions) => {
    return relinkAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::relinkAll
* @see app/Http/Controllers/Household/PreferencesController.php:78
* @route '/household/preferences/paperless/relink-all'
*/
relinkAll.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: relinkAll.url(options),
    method: 'post',
})

const paperless = {
    relinkAll: Object.assign(relinkAll, relinkAll),
}

export default paperless