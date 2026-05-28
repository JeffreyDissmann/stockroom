import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\LanguageController::edit
* @see app/Http/Controllers/Settings/LanguageController.php:16
* @route '/settings/language'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/language',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\LanguageController::edit
* @see app/Http/Controllers/Settings/LanguageController.php:16
* @route '/settings/language'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\LanguageController::edit
* @see app/Http/Controllers/Settings/LanguageController.php:16
* @route '/settings/language'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\LanguageController::edit
* @see app/Http/Controllers/Settings/LanguageController.php:16
* @route '/settings/language'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\LanguageController::update
* @see app/Http/Controllers/Settings/LanguageController.php:26
* @route '/settings/language'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/settings/language',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Settings\LanguageController::update
* @see app/Http/Controllers/Settings/LanguageController.php:26
* @route '/settings/language'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\LanguageController::update
* @see app/Http/Controllers/Settings/LanguageController.php:26
* @route '/settings/language'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const LanguageController = { edit, update }

export default LanguageController