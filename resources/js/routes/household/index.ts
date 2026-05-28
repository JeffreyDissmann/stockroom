import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import backup from './backup'
import importMethod from './import'
import searchIndex from './search-index'
import members from './members'
import invitations from './invitations'
/**
* @see \App\Http\Controllers\Household\ResetController::reset
* @see app/Http/Controllers/Household/ResetController.php:25
* @route '/household/reset'
*/
export const reset = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(options),
    method: 'post',
})

reset.definition = {
    methods: ["post"],
    url: '/household/reset',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\ResetController::reset
* @see app/Http/Controllers/Household/ResetController.php:25
* @route '/household/reset'
*/
reset.url = (options?: RouteQueryOptions) => {
    return reset.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\ResetController::reset
* @see app/Http/Controllers/Household/ResetController.php:25
* @route '/household/reset'
*/
reset.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(options),
    method: 'post',
})

const household = {
    backup: Object.assign(backup, backup),
    import: Object.assign(importMethod, importMethod),
    searchIndex: Object.assign(searchIndex, searchIndex),
    members: Object.assign(members, members),
    reset: Object.assign(reset, reset),
    invitations: Object.assign(invitations, invitations),
}

export default household