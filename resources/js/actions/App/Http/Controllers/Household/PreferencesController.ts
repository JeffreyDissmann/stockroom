import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:25
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
* @see app/Http/Controllers/Household/PreferencesController.php:25
* @route '/household/preferences'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:25
* @route '/household/preferences'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:25
* @route '/household/preferences'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::paperlessParentTargets
* @see app/Http/Controllers/Household/PreferencesController.php:68
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
* @see app/Http/Controllers/Household/PreferencesController.php:68
* @route '/household/preferences/paperless-parent-targets'
*/
paperlessParentTargets.url = (options?: RouteQueryOptions) => {
    return paperlessParentTargets.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::paperlessParentTargets
* @see app/Http/Controllers/Household/PreferencesController.php:68
* @route '/household/preferences/paperless-parent-targets'
*/
paperlessParentTargets.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: paperlessParentTargets.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::paperlessParentTargets
* @see app/Http/Controllers/Household/PreferencesController.php:68
* @route '/household/preferences/paperless-parent-targets'
*/
paperlessParentTargets.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: paperlessParentTargets.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::update
* @see app/Http/Controllers/Household/PreferencesController.php:94
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
* @see app/Http/Controllers/Household/PreferencesController.php:94
* @route '/household/preferences'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::update
* @see app/Http/Controllers/Household/PreferencesController.php:94
* @route '/household/preferences'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

const PreferencesController = { edit, paperlessParentTargets, update }

export default PreferencesController