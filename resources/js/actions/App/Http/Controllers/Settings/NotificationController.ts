import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\NotificationController::edit
* @see app/Http/Controllers/Settings/NotificationController.php:20
* @route '/settings/notifications'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/notifications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\NotificationController::edit
* @see app/Http/Controllers/Settings/NotificationController.php:20
* @route '/settings/notifications'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\NotificationController::edit
* @see app/Http/Controllers/Settings/NotificationController.php:20
* @route '/settings/notifications'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\NotificationController::edit
* @see app/Http/Controllers/Settings/NotificationController.php:20
* @route '/settings/notifications'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\NotificationController::update
* @see app/Http/Controllers/Settings/NotificationController.php:25
* @route '/settings/notifications'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/settings/notifications',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Settings\NotificationController::update
* @see app/Http/Controllers/Settings/NotificationController.php:25
* @route '/settings/notifications'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\NotificationController::update
* @see app/Http/Controllers/Settings/NotificationController.php:25
* @route '/settings/notifications'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const NotificationController = { edit, update }

export default NotificationController