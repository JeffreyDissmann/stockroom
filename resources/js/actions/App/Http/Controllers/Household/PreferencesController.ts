import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:22
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
* @see app/Http/Controllers/Household/PreferencesController.php:22
* @route '/household/preferences'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:22
* @route '/household/preferences'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::edit
* @see app/Http/Controllers/Household/PreferencesController.php:22
* @route '/household/preferences'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\PreferencesController::update
* @see app/Http/Controllers/Household/PreferencesController.php:40
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
* @see app/Http/Controllers/Household/PreferencesController.php:40
* @route '/household/preferences'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\PreferencesController::update
* @see app/Http/Controllers/Household/PreferencesController.php:40
* @route '/household/preferences'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

const PreferencesController = { edit, update }

export default PreferencesController