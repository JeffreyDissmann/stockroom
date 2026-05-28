import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\InvitationController::index
* @see app/Http/Controllers/Household/InvitationController.php:17
* @route '/household/members'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/household/members',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Household\InvitationController::index
* @see app/Http/Controllers/Household/InvitationController.php:17
* @route '/household/members'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\InvitationController::index
* @see app/Http/Controllers/Household/InvitationController.php:17
* @route '/household/members'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\InvitationController::index
* @see app/Http/Controllers/Household/InvitationController.php:17
* @route '/household/members'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\InvitationController::store
* @see app/Http/Controllers/Household/InvitationController.php:41
* @route '/household/invitations'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/household/invitations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\InvitationController::store
* @see app/Http/Controllers/Household/InvitationController.php:41
* @route '/household/invitations'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\InvitationController::store
* @see app/Http/Controllers/Household/InvitationController.php:41
* @route '/household/invitations'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Household\InvitationController::destroy
* @see app/Http/Controllers/Household/InvitationController.php:60
* @route '/household/invitations/{invitation}'
*/
export const destroy = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/household/invitations/{invitation}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Household\InvitationController::destroy
* @see app/Http/Controllers/Household/InvitationController.php:60
* @route '/household/invitations/{invitation}'
*/
destroy.url = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { invitation: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { invitation: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            invitation: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        invitation: typeof args.invitation === 'object'
        ? args.invitation.id
        : args.invitation,
    }

    return destroy.definition.url
            .replace('{invitation}', parsedArgs.invitation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\InvitationController::destroy
* @see app/Http/Controllers/Household/InvitationController.php:60
* @route '/household/invitations/{invitation}'
*/
destroy.delete = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const InvitationController = { index, store, destroy }

export default InvitationController