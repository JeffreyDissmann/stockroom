import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\PreferencesController::relinkAll
* @see app/Http/Controllers/Household/PreferencesController.php:85
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
* @see app/Http/Controllers/Household/PreferencesController.php:85
* @route '/household/preferences/paperless/relink-all'
*/
relinkAll.url = (options?: RouteQueryOptions) => {
    return relinkAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::relinkAll
* @see app/Http/Controllers/Household/PreferencesController.php:85
* @route '/household/preferences/paperless/relink-all'
*/
relinkAll.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: relinkAll.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::refreshMetadata
* @see app/Http/Controllers/Household/PreferencesController.php:97
* @route '/household/preferences/paperless/refresh-metadata'
*/
export const refreshMetadata = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refreshMetadata.url(options),
    method: 'post',
})

refreshMetadata.definition = {
    methods: ["post"],
    url: '/household/preferences/paperless/refresh-metadata',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\PreferencesController::refreshMetadata
* @see app/Http/Controllers/Household/PreferencesController.php:97
* @route '/household/preferences/paperless/refresh-metadata'
*/
refreshMetadata.url = (options?: RouteQueryOptions) => {
    return refreshMetadata.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::refreshMetadata
* @see app/Http/Controllers/Household/PreferencesController.php:97
* @route '/household/preferences/paperless/refresh-metadata'
*/
refreshMetadata.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refreshMetadata.url(options),
    method: 'post',
})

const paperless = {
    relinkAll: Object.assign(relinkAll, relinkAll),
    refreshMetadata: Object.assign(refreshMetadata, refreshMetadata),
}

export default paperless