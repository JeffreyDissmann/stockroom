import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:30
* @route '/household/preferences'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/household/preferences',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:30
* @route '/household/preferences'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:30
* @route '/household/preferences'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:30
* @route '/household/preferences'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::paperlessParentTargets
* @see app/Http/Controllers/Household/PreferencesController.php:131
* @route '/household/preferences/paperless-parent-targets'
*/
export const paperlessParentTargets = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: paperlessParentTargets.url(options),
    method: 'get',
})

paperlessParentTargets.definition = {
    methods: ["get","head"],
    url: '/household/preferences/paperless-parent-targets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Household\PreferencesController::paperlessParentTargets
* @see app/Http/Controllers/Household/PreferencesController.php:131
* @route '/household/preferences/paperless-parent-targets'
*/
paperlessParentTargets.url = (options?: RouteQueryOptions) => {
    return paperlessParentTargets.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::paperlessParentTargets
* @see app/Http/Controllers/Household/PreferencesController.php:131
* @route '/household/preferences/paperless-parent-targets'
*/
paperlessParentTargets.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: paperlessParentTargets.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::paperlessParentTargets
* @see app/Http/Controllers/Household/PreferencesController.php:131
* @route '/household/preferences/paperless-parent-targets'
*/
paperlessParentTargets.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: paperlessParentTargets.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::update
* @see app/Http/Controllers/Household/PreferencesController.php:157
* @route '/household/preferences'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/household/preferences',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Household\PreferencesController::update
* @see app/Http/Controllers/Household/PreferencesController.php:157
* @route '/household/preferences'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::update
* @see app/Http/Controllers/Household/PreferencesController.php:157
* @route '/household/preferences'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::relinkAllPaperless
* @see app/Http/Controllers/Household/PreferencesController.php:84
* @route '/household/preferences/paperless/relink-all'
*/
export const relinkAllPaperless = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: relinkAllPaperless.url(options),
    method: 'post',
})

relinkAllPaperless.definition = {
    methods: ["post"],
    url: '/household/preferences/paperless/relink-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\PreferencesController::relinkAllPaperless
* @see app/Http/Controllers/Household/PreferencesController.php:84
* @route '/household/preferences/paperless/relink-all'
*/
relinkAllPaperless.url = (options?: RouteQueryOptions) => {
    return relinkAllPaperless.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::relinkAllPaperless
* @see app/Http/Controllers/Household/PreferencesController.php:84
* @route '/household/preferences/paperless/relink-all'
*/
relinkAllPaperless.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: relinkAllPaperless.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::refreshPaperlessMetadata
* @see app/Http/Controllers/Household/PreferencesController.php:96
* @route '/household/preferences/paperless/refresh-metadata'
*/
export const refreshPaperlessMetadata = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refreshPaperlessMetadata.url(options),
    method: 'post',
})

refreshPaperlessMetadata.definition = {
    methods: ["post"],
    url: '/household/preferences/paperless/refresh-metadata',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\PreferencesController::refreshPaperlessMetadata
* @see app/Http/Controllers/Household/PreferencesController.php:96
* @route '/household/preferences/paperless/refresh-metadata'
*/
refreshPaperlessMetadata.url = (options?: RouteQueryOptions) => {
    return refreshPaperlessMetadata.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::refreshPaperlessMetadata
* @see app/Http/Controllers/Household/PreferencesController.php:96
* @route '/household/preferences/paperless/refresh-metadata'
*/
refreshPaperlessMetadata.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refreshPaperlessMetadata.url(options),
    method: 'post',
})

const PreferencesController = { edit, paperlessParentTargets, update, relinkAllPaperless, refreshPaperlessMetadata }

export default PreferencesController